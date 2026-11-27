<?php

namespace App\Services\Marketing;

use App\Jobs\GenerateContentPlan;
use App\Models\ContentAgentSetting;
use App\Models\ContentPost;
use App\Models\User;
use App\Services\Ai\AiClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Agent Konten (Tools › Konten Harian): membuat rencana konten sosmed untuk 7–30 hari.
 *
 * Satu rencana = daftar slot (tanggal, jam, format, pilar). Slot dikerjakan antrean 3 per putaran
 * (GenerateContentPlan), jadi satu panggilan AI tetap pendek dan tidak timeout. Hasil AI diperlakukan sebagai data:
 * hanya kolom yang dikenal yang dipakai, dibersihkan dan dibatasi panjangnya. Semua konten mulai sebagai Draf
 * dan baru diproses lebih lanjut setelah disetujui superadmin.
 */
class ContentAgent
{
    public const MAX_DAYS = 31;

    public const MAX_PER_DAY = 3;

    private const BATCH = 3;

    private const MAX_TOKENS = 4000;

    private const PRODUCT = 'bizbos (app.bizbos.id) adalah aplikasi untuk usaha kecil Indonesia: jadwal kelas/booking, data murid, '
        .'tagihan & link pembayaran online (QRIS, virtual account, e-wallet), pengingat & broadcast WhatsApp otomatis, '
        .'halaman website (Pages), katalog online, dan tiket event.';

    /**
     * Siapkan slot rencana lalu jalankan di antrean.
     *
     * @return int jumlah slot yang akan dibuat (slot yang tanggal & jamnya sudah ada konten dilewati)
     */
    public function startPlan(Carbon $start, int $days, int $perDay, User $user): int
    {
        $setting = ContentAgentSetting::current();

        if ($setting->planRunning()) {
            throw new RuntimeException('Rencana sebelumnya masih dibuat. Tunggu sampai selesai dulu ya.');
        }

        AiClient::make(); // AI belum aktif = langsung beri tahu, jangan antrekan pekerjaan yang pasti gagal.

        $days = max(1, min(self::MAX_DAYS, $days));
        $perDay = max(1, min(self::MAX_PER_DAY, $perDay));
        $slots = $this->slots($setting, $start->copy()->startOfDay(), $days, $perDay);

        if ($slots === []) {
            throw new RuntimeException('Semua tanggal & jam di rentang itu sudah punya konten. Pilih tanggal mulai lain.');
        }

        $batch = (string) Str::uuid();
        $setting->forceFill(['plan_state' => [
            'running' => true, 'batch_id' => $batch, 'total' => count($slots), 'done' => 0, 'failed' => 0,
            'slots' => $slots, 'error' => null, 'by' => $user->getKey(),
            'from' => $slots[0]['date'], 'to' => end($slots)['date'],
            'started_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
        ]])->save();

        GenerateContentPlan::dispatch($batch);

        return count($slots);
    }

    /** Hentikan rencana yang sedang dibuat (slot yang belum dikerjakan dibuang, konten yang sudah jadi tetap ada). */
    public function stopPlan(): void
    {
        $setting = ContentAgentSetting::current();
        $state = $setting->plan_state ?? [];

        if (! empty($state['running'])) {
            $setting->forceFill(['plan_state' => ['running' => false, 'slots' => [], 'error' => 'Dihentikan superadmin.', 'updated_at' => now()->toIso8601String()] + $state])->save();
        }
    }

    /**
     * Kerjakan slot berikutnya (dipanggil GenerateContentPlan). Kembalikan true kalau masih ada sisa slot.
     */
    public function generateNext(string $batchId): bool
    {
        return (bool) Cache::lock('content-plan', 300)->get(function () use ($batchId) {
            $setting = ContentAgentSetting::current();
            $state = $setting->plan_state ?? [];

            if (empty($state['running']) || ($state['batch_id'] ?? null) !== $batchId) {
                return false;
            }

            $slots = array_values((array) ($state['slots'] ?? []));
            $chunk = array_slice($slots, 0, self::BATCH);
            $created = 0;
            $error = $state['error'] ?? null;

            try {
                $created = $this->createFromAi($setting, $chunk, $batchId);
            } catch (Throwable $e) {
                // Satu putaran gagal (mis. AI sibuk): slot itu dilewati, sisanya tetap dikerjakan.
                Log::warning('[CONTENT-AGENT] Putaran gagal', ['message' => $e->getMessage()]);
                $error = $e instanceof RuntimeException ? $e->getMessage() : 'Sebagian konten gagal dibuat AI.';
            }

            $rest = array_slice($slots, count($chunk));
            $setting->forceFill(['plan_state' => [
                'running' => $rest !== [],
                'slots' => $rest,
                'done' => (int) ($state['done'] ?? 0) + $created,
                'failed' => (int) ($state['failed'] ?? 0) + (count($chunk) - $created),
                'error' => $error,
                'updated_at' => now()->toIso8601String(),
            ] + $state])->save();

            return $rest !== [];
        });
    }

    /** Tulis ulang satu konten dengan AI (tanggal, jam, format & pilar tetap). Arahan admin opsional. */
    public function regenerate(ContentPost $post, ?string $instruction = null): void
    {
        $setting = ContentAgentSetting::current();
        $slot = ['date' => $post->publish_date->toDateString(), 'time' => $post->publish_time, 'format' => $post->format, 'pillar' => $post->pillar];
        $extra = 'Ini penulisan ulang. Versi sebelumnya berjudul: '.json_encode($post->title, JSON_UNESCAPED_UNICODE).'.'
            .(filled($instruction) ? ' Arahan perbaikan dari admin: '.json_encode(Str::limit(strip_tags($instruction), 500, ''), JSON_UNESCAPED_UNICODE).'.' : ' Buat versi yang lebih menarik.');

        $items = $this->ask($setting, [$slot], $extra);
        $item = $items[0] ?? null;

        if (! $item) {
            throw new RuntimeException('AI tidak memberikan konten yang bisa dipakai. Coba lagi.');
        }

        $post->fill($item)->forceFill(['status' => 'draft', 'approved_at' => null, 'approved_by' => null])->save();
    }

    /** @param  list<array{date: string, time: string, format: string, pillar: string}>  $slots */
    private function createFromAi(ContentAgentSetting $setting, array $slots, string $batchId): int
    {
        $items = $this->ask($setting, $slots);
        $created = 0;

        foreach ($slots as $i => $slot) {
            if (! isset($items[$i])) {
                continue;
            }

            // Slot yang sementara itu sudah terisi (mis. dibuat manual) tidak ditimpa.
            if (ContentPost::whereDate('publish_date', $slot['date'])->where('publish_time', $slot['time'])->exists()) {
                continue;
            }

            ContentPost::create($items[$i] + [
                'batch_id' => $batchId, 'publish_date' => $slot['date'], 'publish_time' => $slot['time'],
                'format' => $slot['format'], 'pillar' => $slot['pillar'],
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Satu panggilan AI untuk beberapa slot. Kembalikan isian bersih per indeks slot.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ask(ContentAgentSetting $setting, array $slots, string $extra = ''): array
    {
        $pillars = collect($setting->pillarList())->keyBy('name');
        $slides = max(3, min(ContentPost::MAX_SLIDES, $setting->carousel_slides ?: 5));
        $input = collect($slots)->values()->map(fn ($s, $i) => [
            'i' => $i,
            'tanggal' => Carbon::parse($s['date'])->translatedFormat('l, d F Y'),
            'jam_tayang' => $s['time'],
            'format' => $s['format'],
            'pilar' => $s['pillar'],
            'keterangan_pilar' => $pillars[$s['pillar']]['description'] ?? '',
            'jumlah_slide' => $s['format'] === 'carousel' ? $slides : null,
        ])->all();

        $recent = ContentPost::latest()->limit(80)->pluck('title')->all();
        $prompt = 'Slot konten: '.json_encode($input, JSON_UNESCAPED_UNICODE)
            ."\nJudul yang SUDAH dipakai (jangan diulang atau dibuat mirip): ".json_encode($recent, JSON_UNESCAPED_UNICODE)
            .($extra !== '' ? "\n".$extra : '');

        $answer = AiClient::make()->complete($this->system($setting), $prompt, self::MAX_TOKENS);
        $json = json_decode((string) preg_replace('/^[^{\[]*|[^}\]]*$/s', '', $answer), true);
        $posts = is_array($json) ? ($json['posts'] ?? (array_is_list($json) ? $json : [])) : [];
        $result = [];

        foreach ($posts as $row) {
            if (! is_array($row) || ! isset($row['i']) || ! is_numeric($row['i'])) {
                continue;
            }

            $i = (int) $row['i'];

            if (! isset($slots[$i]) || isset($result[$i])) {
                continue;
            }

            if ($clean = self::clean($row, $slots[$i]['format'])) {
                $result[$i] = $clean;
            }
        }

        if ($result === []) {
            throw new RuntimeException('Jawaban AI tidak bisa dibaca. Coba lagi atau ganti model AI di Settings › AI.');
        }

        return $result;
    }

    private function system(ContentAgentSetting $setting): string
    {
        return 'Kamu content strategist & copywriter media sosial (Instagram, TikTok, Facebook, YouTube Shorts) untuk '.self::PRODUCT
            ."\nGaya bahasa: ".Str::limit((string) ($setting->brand_voice ?: ContentAgentSetting::DEFAULT_VOICE), 600, '')
            ."\nTarget pembaca: ".Str::limit((string) ($setting->audience ?: ContentAgentSetting::DEFAULT_AUDIENCE), 600, '')
            ."\nFakta & ajakan yang boleh disebut: ".Str::limit((string) ($setting->offer ?: ContentAgentSetting::DEFAULT_OFFER), 800, '')
            ."\nGaya visual: ".Str::limit((string) ($setting->visual_style ?: ContentAgentSetting::DEFAULT_VISUAL), 600, '')
            ."\n\nAturan:"
            ."\n- Bahasa Indonesia. Satu konten = satu ide yang jelas dan berguna, sesuai pilar slotnya."
            ."\n- JANGAN mengarang harga, diskon, angka statistik, testimoni, nama pelanggan, penghargaan, atau fitur yang tidak disebut di atas."
            ."\n- Jangan menyebut atau menjelekkan merek lain. Jangan memakai lirik lagu, musik, tokoh, atau karya berhak cipta."
            ."\n- hook: kalimat pembuka 1 baris yang membuat orang berhenti scroll (maks 120 karakter)."
            ."\n- format reel: script berisi naskah video 20–40 detik, per adegan: \"[Adegan 1 · 0-3 dtk] visual ... | Narasi: ...\", diakhiri ajakan (CTA)."
            ."\n- format carousel: slides berisi tepat jumlah_slide item {\"heading\": maks 60 karakter, \"body\": maks 220 karakter}; slide pertama = hook, slide terakhir = CTA. script boleh kosong."
            ."\n- format image: script berisi teks utama di gambar (maks 2 kalimat pendek)."
            ."\n- caption: 60–180 kata, paragraf pendek, ada CTA, tanpa hashtag. hashtags: 5–10 hashtag relevan dipisah spasi."
            ."\n- visual_prompt: deskripsi visual dalam bahasa Inggris untuk AI pembuat gambar/video, sesuai gaya visual, tanpa logo merek lain."
            ."\n\nJawab HANYA JSON: {\"posts\": [{\"i\": nomor slot, \"title\": \"judul internal singkat\", \"hook\": \"...\", \"script\": \"...\", "
            .'"slides": [{"heading": "...", "body": "..."}], "caption": "...", "hashtags": "#a #b", "visual_prompt": "..."}]}';
    }

    /**
     * Bersihkan satu isian (dari AI atau dari form edit): teks biasa, panjang dibatasi. Null kalau judul kosong.
     *
     * @return array<string, mixed>|null
     */
    public static function clean(array $row, string $format): ?array
    {
        $text = fn ($value, int $max) => mb_substr(trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags(is_scalar($value) ? (string) $value : ''))), 0, $max);
        $title = $text($row['title'] ?? '', 200);

        if ($title === '') {
            return null;
        }

        $slides = null;

        if ($format === 'carousel') {
            $slides = collect(is_array($row['slides'] ?? null) ? $row['slides'] : [])
                ->filter(fn ($s) => is_array($s))
                ->map(fn ($s) => ['heading' => $text($s['heading'] ?? '', 80), 'body' => $text($s['body'] ?? '', 300)])
                ->filter(fn ($s) => $s['heading'] !== '' || $s['body'] !== '')
                ->take(ContentPost::MAX_SLIDES)->values()->all();
        }

        preg_match_all('/#[\p{L}\p{N}_]{2,40}/u', $text($row['hashtags'] ?? '', 1000), $tags);

        return [
            'title' => $title,
            'hook' => $text($row['hook'] ?? '', 300) ?: null,
            'script' => $text($row['script'] ?? '', 4000) ?: null,
            'slides' => $slides ?: null,
            'caption' => $text($row['caption'] ?? '', 2000) ?: null,
            'hashtags' => implode(' ', array_slice(array_unique($tags[0]), 0, 12)) ?: null,
            'visual_prompt' => $text($row['visual_prompt'] ?? '', 1500) ?: null,
        ];
    }

    /**
     * Slot rencana: tiap hari $perDay konten pada jam tayang dari pengaturan. Format dibagi rata menurut persen
     * (mis. 40/40/20), pilar digilir. Tanggal & jam yang sudah punya konten dilewati.
     *
     * @return list<array{date: string, time: string, format: string, pillar: string}>
     */
    private function slots(ContentAgentSetting $setting, Carbon $start, int $days, int $perDay): array
    {
        $times = self::dayTimes($setting->times(), $perDay);
        $mix = $setting->formatMix();
        $total = array_sum($mix);
        $pillars = array_column($setting->pillarList(), 'name') ?: ['Umum'];
        $offset = ContentPost::count();
        $taken = ContentPost::whereBetween('publish_date', [$start->toDateString(), $start->copy()->addDays($days - 1)->toDateString()])
            ->get(['publish_date', 'publish_time'])->map(fn ($p) => $p->publish_date->toDateString().' '.$p->publish_time)->all();

        $assigned = array_fill_keys(array_keys($mix), 0);
        $slots = [];
        $k = 0;

        for ($d = 0; $d < $days; $d++) {
            $date = $start->copy()->addDays($d)->toDateString();

            for ($i = 0; $i < $perDay; $i++) {
                $time = $times[$i];
                $k++;

                // Format dengan "kekurangan" terbesar terhadap target persennya: sebaran rata sepanjang rencana.
                $format = collect($mix)->filter(fn ($pct) => $pct > 0)
                    ->map(fn ($pct, $f) => $pct / $total * $k - $assigned[$f])->sortDesc()->keys()->first();
                $assigned[$format]++;

                if (in_array($date.' '.$time, $taken, true)) {
                    continue;
                }

                $slots[] = ['date' => $date, 'time' => $time, 'format' => $format, 'pillar' => $pillars[($offset + $k) % count($pillars)]];
            }
        }

        return $slots;
    }

    /**
     * Jam tayang untuk $perDay konten per hari. Kalau jam di pengaturan kurang, ditambah 2 jam setelah jam terakhir
     * (paling lambat 21:00) atau 2 jam sebelum jam pertama (paling pagi 07:00), tanpa jam kembar.
     *
     * @param  list<string>  $times
     * @return list<string>
     */
    public static function dayTimes(array $times, int $perDay): array
    {
        $minutes = array_map(fn ($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2), $times);
        sort($minutes);

        while (count($minutes) < $perDay) {
            $later = max($minutes) + 120;
            $earlier = min($minutes) - 120;
            $minutes[] = $later <= 21 * 60 ? $later : ($earlier >= 7 * 60 ? $earlier : intdiv(min($minutes) + max($minutes), 2) + 30);
            $minutes = array_values(array_unique($minutes));
            sort($minutes);
        }

        return array_map(fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60), array_slice($minutes, 0, $perDay));
    }
}
