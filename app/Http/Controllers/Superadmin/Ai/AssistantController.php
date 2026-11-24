<?php

namespace App\Http\Controllers\Superadmin\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiProvider;
use App\Services\Ai\AiChatStream;
use App\Services\Ai\ClassaiRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Tools › Asisten AI: ruang chat superadmin dengan penyedia AI yang aktif (Settings › AI).
 * Aman: hanya mengobrol (tidak ada aksi ke data bizbos), percakapan hanya bisa dibuka pemiliknya,
 * jawaban ditampilkan sebagai teks yang di-escape, dan ada batas pesan per hari + batas panjang jawaban.
 */
class AssistantController extends Controller
{
    /** Batas pesan user per hari (rem biaya kalau ada yang tidak beres). */
    public const DAILY_LIMIT = 200;

    /** Pesan terakhir yang ikut dikirim sebagai konteks (makin banyak = makin mahal). */
    private const HISTORY = 30;

    private const MAX_TOKENS = 4000;

    private const SYSTEM = 'Kamu Asisten AI untuk tim superadmin bizbos, aplikasi untuk usaha kecil Indonesia: jadwal kelas/booking, '
        .'tagihan & pembayaran online, pengingat & broadcast WhatsApp, halaman website (Pages), katalog dan tiket event. '
        .'Bantu terutama urusan marketing harian: ide konten, caption, naskah email/WA penawaran, strategi promosi, dan analisis. '
        .'Jawab dalam bahasa Indonesia yang jelas dan ringkas kecuali diminta lain. Kamu tidak punya akses ke data atau tombol di bizbos '
        .'dan tidak bisa mengirim apa pun: kalau diminta, berikan draf untuk disalin. Jangan mengarang angka atau data bizbos; '
        .'kalau perlu data, minta user menempelkannya.';

    public function index(Request $request, ClassaiRouter $router, ?string $conversation = null): View
    {
        $user = $request->user();
        $current = $conversation ? AiConversation::ownedBy($user)->findOrFail($conversation) : null;
        $provider = AiProvider::active();

        return view('superadmin.ai.assistant', [
            'conversations' => AiConversation::ownedBy($user)->latest('updated_at')->limit(50)->get(['id', 'title', 'updated_at']),
            'current' => $current,
            'messages' => $current ? $current->messages()->get(['id', 'role', 'content', 'model', 'input_tokens', 'output_tokens', 'duration_ms', 'status']) : collect(),
            'provider' => $provider,
            'models' => $provider ? $this->models($provider, $router) : [],
            'model' => $current?->model ?: $provider?->model,
            'usedToday' => $this->usedToday($request),
        ]);
    }

    public function send(Request $request, ClassaiRouter $router, AiChatStream $chat): StreamedResponse|JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
            'conversation_id' => ['nullable', 'uuid'],
            'model' => ['nullable', 'string', 'regex:'.AiProvider::MODEL_PATTERN],
        ], [], ['message' => 'pesan']);

        $provider = AiProvider::active();

        if (! $provider) {
            return response()->json(['message' => 'Belum ada penyedia AI yang aktif. Atur dulu di Settings › AI.'], 422);
        }

        if ($this->usedToday($request) >= self::DAILY_LIMIT) {
            return response()->json(['message' => 'Batas '.self::DAILY_LIMIT.' pesan Asisten AI hari ini sudah tercapai. Besok bisa dipakai lagi.'], 429);
        }

        $model = $data['model'] ?? $provider->model;
        $allowed = array_column($this->models($provider, $router), 'id');

        if (! in_array($model, $allowed, true)) {
            return response()->json(['message' => "Model {$model} tidak tersedia. Pilih model lain dari daftar."], 422);
        }

        $user = $request->user();
        $conversation = filled($data['conversation_id'] ?? null)
            ? AiConversation::ownedBy($user)->findOrFail($data['conversation_id'])
            : tap(new AiConversation(['title' => Str::limit(Str::squish($data['message']), 60, '…')]))->forceFill(['user_id' => $user->getKey()]);

        $conversation->model = $model;
        $conversation->save();
        $conversation->messages()->create(['role' => AiMessage::ROLE_USER, 'content' => $data['message'], 'model' => $model]);
        $history = $this->history($conversation);

        return response()->stream(function () use ($provider, $model, $history, $conversation, $chat) {
            // Simpan jawaban walau tab ditutup di tengah; batas waktu dilonggarkan untuk jawaban panjang.
            ignore_user_abort(true);
            @set_time_limit(300);
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }

            // Tiap potongan jawaban didorong keluar (cara yang sama dengan streaming bawaan Laravel);
            // buffer tidak ditutup, jadi aman juga saat tes membaca isi respons.
            $emit = function (array $event): void {
                echo 'data: '.json_encode($event, JSON_UNESCAPED_UNICODE)."\n\n";

                if (ob_get_level() > 0) {
                    @ob_flush();
                }

                flush();
            };

            $emit(['t' => 'start', 'conversation_id' => $conversation->getKey(), 'title' => $conversation->title, 'url' => route('superadmin.ai-assistant.show', $conversation)]);
            $start = hrtime(true);

            try {
                $result = $chat->stream($provider, $model, $this->system(), $history, self::MAX_TOKENS, function (string $delta) use ($emit) {
                    if (connection_aborted()) {
                        return false;
                    }

                    $emit(['t' => 'delta', 'text' => $delta]);

                    return true;
                });
            } catch (RuntimeException $e) {
                $emit(['t' => 'error', 'message' => $e->getMessage()]);

                return;
            } catch (Throwable $e) {
                report($e);
                $emit(['t' => 'error', 'message' => 'Terjadi kesalahan saat meminta jawaban AI.']);

                return;
            }

            if (trim($result['text']) === '') {
                $emit(['t' => 'error', 'message' => 'AI tidak memberikan jawaban. Coba kirim ulang atau pilih model lain.']);

                return;
            }

            $message = $conversation->messages()->create([
                'role' => AiMessage::ROLE_ASSISTANT,
                'content' => $result['text'],
                'model' => $model,
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
                'duration_ms' => (int) round((hrtime(true) - $start) / 1_000_000),
                'status' => $result['stopped'] ? AiMessage::STATUS_STOPPED : AiMessage::STATUS_DONE,
            ]);
            $conversation->touch();

            $emit(['t' => 'done', 'id' => $message->getKey(), 'model' => $model, 'input_tokens' => $message->input_tokens,
                'output_tokens' => $message->output_tokens, 'duration_ms' => $message->duration_ms, 'status' => $message->status]);
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, private',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function rename(Request $request, string $conversation): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150']], [], ['title' => 'judul']);
        AiConversation::ownedBy($request->user())->findOrFail($conversation)->update($data);

        return back()->with('success', 'Judul percakapan disimpan.');
    }

    public function destroy(Request $request, string $conversation): RedirectResponse
    {
        AiConversation::ownedBy($request->user())->findOrFail($conversation)->delete();

        return redirect()->route('superadmin.ai-assistant.index')->with('success', 'Percakapan dihapus.');
    }

    /**
     * Model yang boleh dipilih: daftar Classai (kalau penyedia aktif Classai dan daftarnya terbaca),
     * selain itu hanya model milik penyedia.
     *
     * @return list<array{id: string, name: string}>
     */
    private function models(AiProvider $provider, ClassaiRouter $router): array
    {
        $own = ['id' => $provider->model, 'name' => $provider->model];

        if (! $provider->isClassai()) {
            return [$own];
        }

        try {
            $models = array_map(fn ($m) => ['id' => $m['id'], 'name' => $m['name']], $router->models($provider));
        } catch (RuntimeException) {
            return [$own];
        }

        return in_array($provider->model, array_column($models, 'id'), true) ? $models : [$own, ...$models];
    }

    /** Riwayat untuk konteks: terakhir HISTORY pesan, peran berurutan user/assistant, diawali user. */
    private function history(AiConversation $conversation): array
    {
        $rows = $conversation->messages()->reorder()->latest('created_at')->latest('id')->limit(self::HISTORY)->get(['role', 'content'])->reverse();
        $messages = [];

        foreach ($rows as $row) {
            $role = $row->role === AiMessage::ROLE_ASSISTANT ? 'assistant' : 'user';

            if ($messages === [] && $role === 'assistant') {
                continue;
            }

            // Dua pesan berperan sama berturut-turut (mis. jawaban sebelumnya gagal) digabung.
            if ($messages !== [] && end($messages)['role'] === $role) {
                $messages[array_key_last($messages)]['content'] .= "\n\n".$row->content;

                continue;
            }

            $messages[] = ['role' => $role, 'content' => $row->content];
        }

        return $messages;
    }

    private function system(): string
    {
        return self::SYSTEM.' Hari ini: '.now()->locale('id')->translatedFormat('l, j F Y').'.';
    }

    private function usedToday(Request $request): int
    {
        return AiMessage::where('role', AiMessage::ROLE_USER)
            ->where('created_at', '>=', today())
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $request->user()->getKey()))
            ->count();
    }
}
