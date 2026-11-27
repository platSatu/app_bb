<?php

namespace App\Http\Controllers\Superadmin\Tools;

use App\Http\Controllers\Controller;
use App\Models\ContentAgentSetting;
use App\Models\ContentPost;
use App\Models\ContentPublication;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Ai\ClassaiMedia;
use App\Services\Ai\MediaCatalog;
use App\Services\Marketing\ContentAgent;
use App\Services\Marketing\ContentMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Superadmin › Tools › Konten Harian: rencana konten dari Agent Konten, persetujuan (sudah dibaca), media otomatis,
 * dan jadwal upload ke akun sosmed (Tools › Akun Sosmed).
 *
 * Aksi massal (setujui, buat media, jadwalkan, batalkan jadwal, hapus) bekerja untuk konten yang dicentang,
 * atau "semua hasil filter" (maks BULK_MAX). Konten yang statusnya tidak cocok untuk aksi itu dilewati.
 */
class ContentController extends Controller
{
    public const BULK_MAX = 500;

    private const PER_PAGE = 30;

    public function __construct(private readonly ContentAgent $agent, private readonly ContentMedia $media) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $setting = ContentAgentSetting::current();

        return view('superadmin.tools.content', [
            'tab' => 'list',
            'filters' => $filters,
            'posts' => $this->filtered($filters)->with('publications.account')
                ->orderBy('publish_date')->orderBy('publish_time')->paginate(self::PER_PAGE)->withQueryString(),
            'total' => $this->filtered($filters)->count(),
            'counts' => $this->filtered(['status' => null] + $filters)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'setting' => $setting,
            'accounts' => SocialAccount::orderBy('platform')->orderBy('name')->get(),
            'mediaReady' => $this->media->provider() !== null,
            'remaining' => $this->media->remainingToday($setting),
        ]);
    }

    public function settings(MediaCatalog $catalog): View
    {
        $setting = ContentAgentSetting::current();
        $provider = $this->media->provider();
        $models = $provider ? $catalog->forProvider($provider) : ['image' => $catalog->images(), 'video' => $catalog->videos()];

        return view('superadmin.tools.content', [
            'tab' => 'settings',
            'setting' => $setting,
            'accounts' => SocialAccount::orderBy('platform')->orderBy('name')->get(),
            'mediaReady' => $provider !== null,
            'remaining' => $this->media->remainingToday($setting),
            'imageModels' => $models['image'],
            'videoModels' => $models['video'],
            'prices' => collect(ContentPost::FORMATS)->keys()->mapWithKeys(fn ($f) => [$f => $this->media->priceHint($setting, $f)]),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pillars' => ['required', 'string', 'max:4000'],
            'brand_voice' => ['nullable', 'string', 'max:1000'],
            'audience' => ['nullable', 'string', 'max:1000'],
            'offer' => ['nullable', 'string', 'max:1500'],
            'visual_style' => ['nullable', 'string', 'max:1000'],
            'formats' => ['required', 'array'],
            'formats.*' => ['nullable', 'integer', 'between:0,100'],
            'post_times' => ['required', 'string', 'max:100'],
            'auto_media' => ['nullable', 'boolean'],
            'image_model' => ['required', 'string', 'regex:'.\App\Models\AiProvider::MODEL_PATTERN],
            'video_model' => ['required', 'string', 'regex:'.\App\Models\AiProvider::MODEL_PATTERN],
            'video_resolution' => ['required', 'string', 'regex:/^\d{3,4}p$/'],
            'video_seconds' => ['required', 'integer', 'between:3,30'],
            'carousel_slides' => ['required', 'integer', 'between:3,'.ContentPost::MAX_SLIDES],
            'media_daily_limit' => ['required', 'integer', 'between:0,200'],
        ]);

        $pillars = collect(preg_split('/\R/', $data['pillars']))->map(function ($line) {
            [$name, $description] = array_pad(explode(':', strip_tags($line), 2), 2, '');
            $name = mb_substr(trim($name), 0, 60);

            return $name !== '' ? ['name' => $name, 'description' => mb_substr(trim($description), 0, 300)] : null;
        })->filter()->unique('name')->take(12)->values()->all();

        preg_match_all('/\b([01]?\d|2[0-3])[.:]([0-5]\d)\b/', $data['post_times'], $m, PREG_SET_ORDER);
        $times = collect($m)->map(fn ($t) => sprintf('%02d:%02d', $t[1], $t[2]))->unique()->sort()->take(ContentAgent::MAX_PER_DAY)->values()->all();
        $formats = collect(ContentPost::FORMATS)->keys()->mapWithKeys(fn ($f) => [$f => (int) ($data['formats'][$f] ?? 0)])->all();

        if (! $pillars || ! $times || array_sum($formats) === 0) {
            return back()->withInput()->with('error', 'Isi minimal satu pilar ("Nama: keterangan"), satu jam tayang (mis. 11:30), dan persen format yang tidak semuanya 0.');
        }

        $text = fn (?string $v, int $max) => filled($v) ? mb_substr(trim(strip_tags($v)), 0, $max) : null;

        ContentAgentSetting::current()->update([
            'pillars' => $pillars,
            'brand_voice' => $text($data['brand_voice'] ?? null, 1000),
            'audience' => $text($data['audience'] ?? null, 1000),
            'offer' => $text($data['offer'] ?? null, 1500),
            'visual_style' => $text($data['visual_style'] ?? null, 1000),
            'formats' => $formats,
            'post_times' => $times,
            'auto_media' => $request->boolean('auto_media'),
            'image_model' => $data['image_model'],
            'video_model' => $data['video_model'],
            'video_resolution' => $data['video_resolution'],
            'video_seconds' => $data['video_seconds'],
            'carousel_slides' => $data['carousel_slides'],
            'media_daily_limit' => $data['media_daily_limit'],
            'updated_by' => $request->user()->getKey(),
        ]);

        return back()->with('success', 'Pengaturan Agent Konten disimpan.');
    }

    /** Buat rencana 1–31 hari (antrean). */
    public function plan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:today', 'before:'.now()->addYear()->toDateString()],
            'days' => ['required', 'integer', 'between:1,'.ContentAgent::MAX_DAYS],
            'per_day' => ['required', 'integer', 'between:1,'.ContentAgent::MAX_PER_DAY],
        ]);

        try {
            $count = $this->agent->startPlan(Carbon::parse($data['start_date']), (int) $data['days'], (int) $data['per_day'], $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('superadmin.tools.content.index')
            ->with('success', "Agent sedang membuat {$count} konten. Daftar terisi bertahap, biasanya beberapa menit.");
    }

    public function planStatus(): JsonResponse
    {
        $setting = ContentAgentSetting::current();
        $state = $setting->plan_state ?? [];

        return response()->json([
            'running' => $setting->planRunning(),
            'done' => (int) ($state['done'] ?? 0),
            'failed' => (int) ($state['failed'] ?? 0),
            'total' => (int) ($state['total'] ?? 0),
            'error' => $state['error'] ?? null,
        ]);
    }

    public function stopPlan(): RedirectResponse
    {
        $this->agent->stopPlan();

        return back()->with('success', 'Pembuatan rencana dihentikan. Konten yang sudah jadi tetap ada.');
    }

    public function update(Request $request, ContentPost $post): RedirectResponse
    {
        if (! $post->isEditable()) {
            return back()->with('error', 'Konten yang sudah dijadwalkan / diposting tidak bisa diubah. Batalkan jadwalnya dulu.');
        }

        $data = $request->validate([
            'publish_date' => ['required', 'date'],
            'publish_time' => ['required', 'date_format:H:i'],
            'title' => ['required', 'string', 'max:200'],
            'hook' => ['nullable', 'string', 'max:300'],
            'script' => ['nullable', 'string', 'max:4000'],
            'slides' => ['nullable', 'string', 'max:5000'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'hashtags' => ['nullable', 'string', 'max:1000'],
            'visual_prompt' => ['nullable', 'string', 'max:1500'],
        ]);

        $slides = collect(preg_split('/\R/', (string) ($data['slides'] ?? '')))->map(function ($line) {
            [$heading, $body] = array_pad(explode('|', $line, 2), 2, '');

            return ['heading' => $heading, 'body' => $body];
        })->all();

        $clean = ContentAgent::clean(['slides' => $slides] + $data, $post->format);
        $post->fill($clean + ['publish_date' => $data['publish_date'], 'publish_time' => $data['publish_time']])->save();

        return back()->with('success', 'Konten "'.$post->title.'" disimpan.');
    }

    public function regenerate(Request $request, ContentPost $post): RedirectResponse
    {
        $data = $request->validate(['instruction' => ['nullable', 'string', 'max:500']]);

        if (! $post->isEditable()) {
            return back()->with('error', 'Konten yang sudah dijadwalkan / diposting tidak bisa ditulis ulang.');
        }

        try {
            $this->agent->regenerate($post, $data['instruction'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Konten ditulis ulang AI dan kembali ke Draf. Baca lagi lalu setujui.');
    }

    /** Buat / buat ulang media satu konten. */
    public function media(ContentPost $post): RedirectResponse
    {
        $error = $this->media->queue($post);

        return $error ? back()->with('error', $error) : back()->with('success', 'Media sedang dibuat. Gambar biasanya < 1 menit, video beberapa menit.');
    }

    /** Ganti media dengan file sendiri. */
    public function upload(Request $request, ContentPost $post): RedirectResponse
    {
        if (! in_array($post->status, ['approved', 'ready'], true)) {
            return back()->with('error', 'Setujui kontennya dulu (dan belum dijadwalkan) sebelum mengganti media.');
        }

        $rules = $post->isVideo()
            ? ['files' => ['required', 'array', 'size:1'], 'files.*' => ['file', 'mimetypes:video/mp4,video/quicktime', 'max:307200']]
            : ['files' => ['required', 'array', $post->format === 'carousel' ? 'between:2,'.ContentPost::MAX_SLIDES : 'size:1'],
                'files.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=320,min_height=320,max_width=6000,max_height=6000']];

        $request->validate($rules, [
            'files.size' => $post->isVideo() ? 'Unggah tepat 1 video MP4/MOV.' : 'Unggah tepat 1 gambar.',
            'files.between' => 'Carousel butuh 2–'.ContentPost::MAX_SLIDES.' gambar.',
        ], ['files' => 'file media', 'files.*' => 'file media']);

        if (in_array($post->media_status, ['queued', 'processing'], true)) {
            return back()->with('error', 'Media konten ini sedang dibuat AI. Tunggu selesai dulu.');
        }

        try {
            $this->media->replaceWithUpload($post, $request->file('files'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Media diganti dengan file Anda.');
    }

    public function schedule(Request $request, ContentPost $post): RedirectResponse
    {
        $data = $request->validate($this->scheduleRules() + [
            'accounts' => ['required', 'array', 'min:1'],
            'accounts.*' => ['uuid'],
        ], [], ['accounts' => 'akun tujuan']);

        $error = $this->scheduleOne($post, $data['accounts'], $this->tiktokOptions($data), $request->user());

        return $error ? back()->withInput()->with('error', $error)
            : back()->with('success', 'Dijadwalkan '.$post->fresh()->scheduled_at->translatedFormat('d M Y H:i').' WIB ke '.count($data['accounts']).' akun.');
    }

    public function unschedule(ContentPost $post): RedirectResponse
    {
        return $this->unscheduleOne($post)
            ? back()->with('success', 'Jadwal dibatalkan.')
            : back()->with('error', 'Jadwal tidak bisa dibatalkan: kiriman sedang berjalan atau sudah selesai.');
    }

    /** Ulangi kiriman yang gagal (superadmin sudah mengecek akunnya). */
    public function retry(ContentPublication $publication): RedirectResponse
    {
        $post = $publication->post;

        if (! $post?->hasMedia() || ! $publication->account?->isConnected()) {
            return back()->with('error', 'Tidak bisa diulang: media belum ada atau akun tujuan terputus.');
        }

        if (! $publication->claim('failed', 'pending')) {
            return back()->with('error', 'Kiriman ini tidak dalam status gagal.');
        }

        $publication->forceFill(['remote_id' => null, 'error' => null])->save();
        $post->forceFill(['status' => 'scheduled', 'scheduled_at' => $post->scheduled_at && $post->scheduled_at->isFuture() ? $post->scheduled_at : now()])->save();

        return back()->with('success', 'Kiriman ke '.$publication->account->platformLabel().' diulang dalam 1 menit.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate($this->scheduleRules() + [
            'action' => ['required', Rule::in(['approve', 'unapprove', 'media', 'schedule', 'unschedule', 'delete'])],
            'all' => ['nullable', 'boolean'],
            'ids' => ['required_unless:all,1', 'array', 'max:'.self::BULK_MAX],
            'ids.*' => ['uuid'],
        ], ['ids.required_unless' => 'Centang dulu konten yang mau diproses.']);

        $query = $request->boolean('all')
            ? $this->filtered($this->filters($request))
            : ContentPost::whereIn('id', $data['ids'] ?? []);
        $posts = $query->orderBy('publish_date')->orderBy('publish_time')->limit(self::BULK_MAX)->get();
        $user = $request->user();

        return match ($data['action']) {
            'approve' => $this->bulkApprove($posts, $user),
            'unapprove' => back()->with('success', $posts->filter(fn ($p) => in_array($p->status, ['approved', 'ready'], true))
                ->each(fn ($p) => $p->forceFill(['status' => 'draft', 'approved_at' => null, 'approved_by' => null])->save())->count().' konten dikembalikan ke Draf.'),
            'media' => $this->bulkMedia($posts),
            'schedule' => $this->bulkSchedule($posts, $this->tiktokOptions($data), $user),
            'unschedule' => back()->with('success', $posts->filter(fn ($p) => $this->unscheduleOne($p))->count().' jadwal dibatalkan.'),
            'delete' => $this->bulkDelete($posts),
        };
    }

    /** Pratinjau media untuk superadmin (file privat). */
    public function file(ContentPost $post, int $index): StreamedResponse
    {
        $file = $post->mediaFiles()[$index] ?? abort(404);
        $disk = Storage::disk(ClassaiMedia::DISK);
        abort_unless($disk->exists($file['path']), 404);

        return $disk->response($file['path'], basename($file['path']), ['Content-Type' => $file['mime'], 'Cache-Control' => 'private, max-age=600']);
    }

    private function bulkApprove($posts, User $user): RedirectResponse
    {
        $approved = $posts->where('status', 'draft')->each(fn (ContentPost $p) => $p->forceFill([
            'status' => $p->hasMedia() ? 'ready' : 'approved', 'approved_at' => now(), 'approved_by' => $user->getKey(),
        ])->save());

        $queued = 0;

        if (ContentAgentSetting::current()->auto_media) {
            foreach ($approved->filter(fn ($p) => $p->status === 'approved' && $p->media_status === null) as $post) {
                if ($this->media->queue($post) !== null) {
                    break; // kuota habis / Classai belum aktif: sisanya dikerjakan bertahap oleh content:publish
                }

                $queued++;
            }
        }

        $rest = $approved->count() - $queued;

        return back()->with('success', $approved->count().' konten disetujui.'.($queued ? " Media sedang dibuat untuk {$queued} konten." : '')
            .($rest > 0 && ContentAgentSetting::current()->auto_media ? " Sisanya dibuat bertahap sesuai batas media per hari." : ''));
    }

    private function bulkMedia($posts): RedirectResponse
    {
        $queued = 0;
        $error = null;

        foreach ($posts->filter(fn ($p) => in_array($p->status, ['approved', 'ready'], true)) as $post) {
            if ($error = $this->media->queue($post)) {
                if (str_contains($error, 'Batas') || str_contains($error, 'Classai')) {
                    break;
                }

                $error = null;
                continue;
            }

            $queued++;
        }

        return back()->with($queued ? 'success' : 'error', $queued ? "Media sedang dibuat untuk {$queued} konten.".($error ? ' '.$error : '') : ($error ?? 'Tidak ada konten disetujui yang bisa dibuatkan media.'));
    }

    private function bulkSchedule($posts, array $tiktok, User $user): RedirectResponse
    {
        $defaults = SocialAccount::connected()->where('is_default', true)->get();

        if ($defaults->isEmpty()) {
            return back()->with('error', 'Belum ada akun tujuan bawaan yang tersambung (Tools › Akun Sosmed), atau jadwalkan satu per satu.');
        }

        $done = 0;
        $errors = [];

        foreach ($posts->filter(fn ($p) => $p->status === 'ready') as $post) {
            // Privasi TikTok belum dipilih: TikTok dilewati, akun lain tetap dijadwalkan.
            $accounts = $defaults->whereIn('platform', $post->allowedPlatforms())
                ->reject(fn ($a) => $a->platform === 'tiktok' && blank($tiktok['privacy'] ?? null))->modelKeys();
            $error = $accounts ? $this->scheduleOne($post, $accounts, $tiktok, $user) : 'tidak ada akun bawaan yang cocok untuk format ini';

            if ($error) {
                $errors[$error] = true;
            } else {
                $done++;
            }
        }

        $message = $done.' konten dijadwalkan.'.($errors ? ' Sebagian dilewati: '.implode('; ', array_keys($errors)) : '');

        return back()->with($done ? 'success' : 'error', $done ? $message : ($errors ? 'Tidak ada yang dijadwalkan: '.implode('; ', array_keys($errors)) : 'Pilih konten berstatus "Siap upload".'));
    }

    private function bulkDelete($posts): RedirectResponse
    {
        $deleted = 0;

        foreach ($posts as $post) {
            if (in_array($post->status, ['scheduled'], true) || $post->publications()->whereIn('status', ContentPublication::ACTIVE)->exists()
                || in_array($post->media_status, ['queued', 'processing'], true)) {
                continue;
            }

            ContentMedia::deleteFiles($post->mediaFiles());
            $post->delete();
            $deleted++;
        }

        return back()->with('success', $deleted.' konten dihapus.'.($deleted < $posts->count() ? ' Konten yang terjadwal / sedang dibuat medianya dilewati.' : ''));
    }

    /**
     * Jadwalkan satu konten ke akun-akun tertentu. Null = berhasil, string = alasan gagal.
     *
     * @param  list<string>  $accountIds
     */
    private function scheduleOne(ContentPost $post, array $accountIds, array $tiktok, User $user): ?string
    {
        if (! in_array($post->status, ['ready', 'scheduled'], true) || ! $post->hasMedia()) {
            return 'konten belum siap (setujui & tunggu media jadi dulu)';
        }

        $accounts = SocialAccount::connected()->whereIn('id', $accountIds)->get();

        if ($accounts->isEmpty() || $accounts->count() !== count(array_unique($accountIds))) {
            return 'akun tujuan tidak ditemukan atau terputus';
        }

        if ($wrong = $accounts->first(fn ($a) => ! in_array($a->platform, $post->allowedPlatforms(), true))) {
            return $wrong->platformLabel().' tidak menerima format '.$post->formatLabel();
        }

        foreach ($accounts->where('platform', 'tiktok') as $account) {
            if (! in_array($tiktok['privacy'] ?? '', $account->tiktokPrivacyOptions(), true)) {
                return 'pilih privasi TikTok yang tersedia untuk akun '.$account->displayName();
            }
        }

        if ($post->isVideo() && count($post->mediaFiles()) !== 1) {
            return 'video harus tepat 1 file';
        }

        $when = $post->plannedAt()->isPast() ? now() : $post->plannedAt();

        DB::transaction(function () use ($post, $accounts, $tiktok, $user, $when) {
            ContentPost::whereKey($post->getKey())->lockForUpdate()->first();

            // Akun yang tidak dipilih lagi: kiriman yang belum berjalan dibuang.
            $post->publications()->where('status', 'pending')->whereNotIn('social_account_id', $accounts->modelKeys())->delete();

            foreach ($accounts as $account) {
                $post->publications()->firstOrCreate(['social_account_id' => $account->getKey()], ['platform' => $account->platform]);
            }

            $post->forceFill([
                'status' => 'scheduled',
                'scheduled_at' => $when,
                'scheduled_by' => $user->getKey(),
                'options' => array_merge($post->options ?? [], $accounts->contains('platform', 'tiktok') ? ['tiktok' => $tiktok] : []),
            ])->save();
        });

        return null;
    }

    private function unscheduleOne(ContentPost $post): bool
    {
        if ($post->status !== 'scheduled' || $post->publications()->whereIn('status', ['publishing', 'processing'])->exists()) {
            return false;
        }

        $post->publications()->where('status', 'pending')->delete();
        $post->forceFill(['scheduled_at' => null])->save();
        $post->refreshPublishStatus();

        // Masih ada kiriman yang selesai/gagal: status mengikuti hasilnya; tidak ada sama sekali: kembali siap upload.
        if ($post->fresh()->status === 'scheduled') {
            $post->forceFill(['status' => 'ready'])->save();
        }

        return true;
    }

    private function scheduleRules(): array
    {
        return [
            'tiktok_privacy' => ['nullable', Rule::in(['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'])],
            'tiktok_comment' => ['nullable', 'boolean'],
            'tiktok_duet' => ['nullable', 'boolean'],
            'tiktok_stitch' => ['nullable', 'boolean'],
        ];
    }

    private function tiktokOptions(array $data): array
    {
        return [
            'privacy' => $data['tiktok_privacy'] ?? null,
            'comment' => (bool) ($data['tiktok_comment'] ?? false),
            'duet' => (bool) ($data['tiktok_duet'] ?? false),
            'stitch' => (bool) ($data['tiktok_stitch'] ?? false),
        ];
    }

    /** @return array{status: ?string, format: ?string, from: ?string, to: ?string, search: ?string} */
    private function filters(Request $request): array
    {
        $status = $request->input('status');
        $format = $request->input('format');
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        $any = $request->hasAny(['status', 'format', 'from', 'to', 'search']) || $request->query->has('from') || $request->request->has('from');

        return [
            'status' => is_string($status) && isset(ContentPost::STATUSES[$status]) ? $status : null,
            'format' => is_string($format) && isset(ContentPost::FORMATS[$format]) ? $format : null,
            // Tanpa filter: mulai 3 hari lalu, supaya yang sudah lama terposting tidak memenuhi daftar.
            'from' => $date($request->input('from')) ?? ($any ? null : today()->subDays(3)->toDateString()),
            'to' => $date($request->input('to')),
            'search' => is_string($request->input('search')) ? mb_substr(trim($request->input('search')), 0, 100) : null,
        ];
    }

    private function filtered(array $filters): Builder
    {
        return ContentPost::query()
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['format'] ?? null, fn ($q, $f) => $q->where('format', $f))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('publish_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('publish_date', '<=', $d))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', '%'.addcslashes($s, '%_\\').'%')
                ->orWhere('caption', 'like', '%'.addcslashes($s, '%_\\').'%')->orWhere('pillar', 'like', '%'.addcslashes($s, '%_\\').'%')));
    }
}
