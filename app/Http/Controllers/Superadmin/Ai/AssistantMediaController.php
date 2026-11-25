<?php

namespace App\Http\Controllers\Superadmin\Ai;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAiMedia;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiProvider;
use App\Services\Ai\ClassaiMedia;
use App\Services\Ai\MediaCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tools › Asisten AI: gambar, video & suara dari Classai Router.
 * Permintaan disimpan sebagai pesan "pending" lalu dikerjakan antrean (GenerateAiMedia); halaman chat menanyakan
 * statusnya berkala. File hasil disimpan privat dan hanya bisa dibuka pemilik percakapan.
 */
class AssistantMediaController extends Controller
{
    /** Batas per user per hari, per jenis (rem biaya). */
    public const DAILY_LIMITS = [AiMessage::KIND_IMAGE => 40, AiMessage::KIND_VIDEO => 10, AiMessage::KIND_AUDIO => 40];

    /** Gambar/suara yang "processing" lebih lama dari ini dianggap macet (worker mati di tengah jalan). */
    private const STALE_MINUTES = 10;

    public function options(Request $request, MediaCatalog $catalog): JsonResponse
    {
        $provider = $this->classai();

        if ($provider instanceof JsonResponse) {
            return $provider;
        }

        return response()->json([
            ...$catalog->forProvider($provider),
            'image_aspects' => MediaCatalog::IMAGE_ASPECTS,
            'video_aspects' => MediaCatalog::VIDEO_ASPECTS,
            'styles' => MediaCatalog::VOICE_STYLES,
            'qualities' => MediaCatalog::QUALITIES,
            'used' => collect(self::DAILY_LIMITS)->map(fn ($limit, $kind) => ['used' => $this->usedToday($request, $kind), 'limit' => $limit]),
        ]);
    }

    public function voices(Request $request, MediaCatalog $catalog, ClassaiMedia $media): JsonResponse
    {
        $provider = $this->classai();

        if ($provider instanceof JsonResponse) {
            return $provider;
        }

        $model = (string) $request->query('model');

        if (! $catalog->find($provider, AiMessage::KIND_AUDIO, $model)) {
            return response()->json(['message' => 'Mesin suara tidak dikenal.'], 422);
        }

        try {
            $voices = Cache::remember('classai:voices:'.$provider->getKey().':'.$model, 600, fn () => $media->voices($provider, $model));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['voices' => $voices]);
    }

    public function generate(Request $request, MediaCatalog $catalog, ClassaiMedia $media): JsonResponse
    {
        $provider = $this->classai();

        if ($provider instanceof JsonResponse) {
            return $provider;
        }

        $kind = $request->validate(['kind' => ['required', Rule::in(AiMessage::MEDIA_KINDS)]])['kind'];
        $model = $catalog->find($provider, $kind, (string) $request->input('model'));

        if (! $model) {
            return response()->json(['message' => 'Model tidak tersedia. Pilih model lain dari daftar.'], 422);
        }

        $data = $request->validate($this->rules($kind, $model), [
            'reference.required' => 'Model ini perlu 1 gambar untuk diedit. Lampirkan gambarnya dulu.',
        ], ['prompt' => $kind === AiMessage::KIND_AUDIO ? 'teks' : 'deskripsi', 'reference' => 'gambar referensi', 'voice' => 'suara']);

        if ($this->usedToday($request, $kind) >= self::DAILY_LIMITS[$kind]) {
            return response()->json(['message' => 'Batas '.self::DAILY_LIMITS[$kind].' '.$this->label($kind).' per hari sudah tercapai. Besok bisa dipakai lagi.'], 429);
        }

        $user = $request->user();
        $conversation = filled($data['conversation_id'] ?? null)
            ? AiConversation::ownedBy($user)->findOrFail($data['conversation_id'])
            : tap(new AiConversation(['title' => Str::limit(Str::squish($this->label($kind).': '.$data['prompt']), 60, '…')]))->forceFill(['user_id' => $user->getKey()]);
        $conversation->save();

        $options = array_filter([
            'prompt' => $data['prompt'],
            'aspect_ratio' => $data['aspect_ratio'] ?? null,
            'resolution' => $data['resolution'] ?? null,
            'quality' => $data['quality'] ?? null,
            'n' => isset($data['n']) ? (int) $data['n'] : null,
            'seconds' => isset($data['seconds']) ? (int) $data['seconds'] : null,
            'voice' => $data['voice'] ?? null,
            'voice_name' => isset($data['voice_name']) ? Str::limit(strip_tags($data['voice_name']), 60, '') : null,
            'style' => $data['style'] ?? null,
            'speed' => isset($data['speed']) ? (float) $data['speed'] : null,
            'reference' => $request->hasFile('reference')
                ? $request->file('reference')->store('ai-media/'.$conversation->getKey().'/ref', ClassaiMedia::DISK)
                : null,
        ], fn ($v) => $v !== null);

        $userMessage = $conversation->messages()->create([
            'role' => AiMessage::ROLE_USER, 'kind' => $kind, 'content' => $data['prompt'], 'model' => $model['id'],
            'options' => collect($options)->except(['prompt', 'reference'])->all(),
        ]);
        $answer = $conversation->messages()->create([
            'role' => AiMessage::ROLE_ASSISTANT, 'kind' => $kind, 'content' => '', 'model' => $model['id'],
            'options' => $options, 'status' => AiMessage::STATUS_PENDING,
        ]);
        $conversation->touch();

        GenerateAiMedia::dispatch($answer->getKey());

        return response()->json([
            'conversation' => ['id' => $conversation->getKey(), 'title' => $conversation->title, 'url' => route('superadmin.ai-assistant.show', $conversation)],
            'user' => $userMessage->toChatArray(),
            'assistant' => $answer->fresh()->toChatArray(),
        ]);
    }

    public function status(Request $request, string $message): JsonResponse
    {
        $message = $this->owned($request, $message);

        if ($message->isWaiting()) {
            // Antrean "sync" (lokal): video dicek di sini, paling sering sekali per 15 detik.
            if ($message->kind === AiMessage::KIND_VIDEO && $message->job_id && config('queue.default') === 'sync'
                && Cache::add('ai-media-poll:'.$message->getKey(), 1, GenerateAiMedia::POLL_SECONDS)) {
                GenerateAiMedia::dispatchSync($message->getKey());
                $message->refresh();
            }

            // Gambar/suara yang tertahan terlalu lama: worker kemungkinan berhenti di tengah jalan.
            if ($message->kind !== AiMessage::KIND_VIDEO && $message->status === AiMessage::STATUS_PROCESSING
                && $message->updated_at->lt(now()->subMinutes(self::STALE_MINUTES))) {
                $message->forceFill(['status' => AiMessage::STATUS_FAILED, 'content' => 'Proses terhenti di tengah jalan. Silakan buat ulang.'])->save();
            }
        }

        return response()->json([
            'message' => $message->toChatArray(),
            'queued_minutes' => $message->status === AiMessage::STATUS_PENDING ? (int) $message->created_at->diffInMinutes(now()) : 0,
        ]);
    }

    public function file(Request $request, string $message, int $index): StreamedResponse
    {
        $message = $this->owned($request, $message);
        $file = ((array) $message->media)[$index] ?? null;
        $disk = Storage::disk(ClassaiMedia::DISK);

        abort_unless($file && $disk->exists($file['path']), 404);

        $name = 'bizbos-'.$message->kind.'-'.$message->created_at->format('Ymd-His').'-'.($index + 1).'.'.pathinfo($file['path'], PATHINFO_EXTENSION);
        $headers = ['Content-Type' => $file['mime'], 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=86400'];

        return $request->boolean('download')
            ? $disk->download($file['path'], $name, $headers)
            : $disk->response($file['path'], $name, $headers);
    }

    /** Aturan isian sesuai jenis & kemampuan model (rasio, resolusi, durasi, mutu, referensi). */
    private function rules(string $kind, array $model): array
    {
        $rules = [
            'conversation_id' => ['nullable', 'uuid'],
            'prompt' => ['required', 'string', 'max:'.($kind === AiMessage::KIND_AUDIO ? ($model['max_chars'] ?? 4000) : 4000)],
        ];

        if ($kind === AiMessage::KIND_AUDIO) {
            return [...$rules,
                'voice' => ['required', 'string', 'regex:'.AiProvider::MODEL_PATTERN],
                'voice_name' => ['nullable', 'string', 'max:120'],
                'style' => ['nullable', Rule::in(array_keys(MediaCatalog::VOICE_STYLES))],
                'speed' => ['nullable', 'numeric', 'between:0.7,1.2'],
            ];
        }

        $reference = ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'];
        $rules['aspect_ratio'] = ['required', Rule::in($model['aspects'])];
        $rules['resolution'] = ['nullable', Rule::in($model['resolutions'])];

        if ($kind === AiMessage::KIND_IMAGE) {
            $rules['n'] = ['nullable', 'integer', 'between:1,4'];
            $rules['quality'] = $model['quality'] ? ['nullable', Rule::in(array_keys(MediaCatalog::QUALITIES))] : ['prohibited'];
            $rules['reference'] = match ($model['reference']) {
                'required' => ['required', ...$reference],
                'none' => ['prohibited'],
                default => ['nullable', ...$reference],
            };

            return $rules;
        }

        $seconds = $model['seconds'];
        $rules['seconds'] = isset($seconds['list'])
            ? ['required', 'integer', Rule::in($seconds['list'])]
            : ['required', 'integer', 'between:'.$seconds['min'].','.$seconds['max']];
        $rules['reference'] = ['nullable', ...$reference];

        return $rules;
    }

    private function classai(): AiProvider|JsonResponse
    {
        $provider = AiProvider::active();

        return $provider?->isClassai()
            ? $provider
            : response()->json(['message' => 'Gambar, video & suara hanya tersedia kalau penyedia AI yang aktif adalah Classai Router (Settings › AI).'], 422);
    }

    private function owned(Request $request, string $id): AiMessage
    {
        abort_unless(Str::isUuid($id), 404);

        return AiMessage::whereKey($id)
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $request->user()->getKey()))
            ->firstOrFail();
    }

    private function usedToday(Request $request, string $kind): int
    {
        return AiMessage::where('role', AiMessage::ROLE_USER)->where('kind', $kind)
            ->where('created_at', '>=', today())
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $request->user()->getKey()))
            ->count();
    }

    private function label(string $kind): string
    {
        return ['image' => 'gambar', 'video' => 'video', 'audio' => 'suara'][$kind] ?? $kind;
    }
}
