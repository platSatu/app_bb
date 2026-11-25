<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Daftar model gambar, video & mesin suara Classai Router untuk Asisten AI.
 *
 * Isi dasarnya dari tabel dokumentasi Classai (router.classai.id/dashboard/docs: Images, Videos, Voice, Nov 2026),
 * karena GET /v1/media/models belum dijelaskan bentuk balasannya. Kalau API bisa dibaca, daftar ini disaring
 * ke model yang benar-benar aktif (dan model baru dari API ikut tampil dengan pilihan umum).
 * Harga ditampilkan dalam Rupiah perkiraan: 1 juta kredit = Rp150.
 */
class MediaCatalog
{
    private const TTL = 600;

    public const IMAGE_ASPECTS = [
        '1:1' => '1:1 · kotak (feed IG)', '4:5' => '4:5 · feed potret', '9:16' => '9:16 · story/reels',
        '16:9' => '16:9 · lanskap/YouTube', '3:4' => '3:4', '2:3' => '2:3', '4:3' => '4:3', '3:2' => '3:2', '21:9' => '21:9 · banner lebar',
    ];

    public const VIDEO_ASPECTS = ['16:9' => '16:9 · lanskap', '9:16' => '9:16 · story/reels', '1:1' => '1:1 · kotak', '4:3' => '4:3', '3:4' => '3:4', '21:9' => '21:9 · sinematik'];

    public const VOICE_STYLES = ['natural' => 'Natural', 'narasi' => 'Narasi', 'iklan' => 'Iklan', 'santai' => 'Santai', 'berita' => 'Berita', 'edukasi' => 'Edukasi'];

    public const QUALITIES = ['low' => 'Rendah (hemat)', 'medium' => 'Sedang', 'high' => 'Tinggi (tajam)'];

    /** GPT Image 1.x hanya mendukung 1:1, 3:2 dan 2:3. */
    private const GPT1_ASPECTS = ['1:1', '3:2', '2:3'];

    /** id => [nama, resolusi, harga, waktu, opsi tambahan] */
    private const IMAGES = [
        'flux-2-klein' => ['FLUX.2 Klein · paling hemat & cepat', ['1K', '2K'], '±Rp495', '2–6 dtk'],
        'seedream-5-flash' => ['Seedream 5.0 Flash', ['1K', '2K'], '±Rp555', '15–20 dtk'],
        'nano-banana-2-lite' => ['Nano Banana 2 Lite · Google', ['1K'], '±Rp795', '5–10 dtk'],
        'nano-banana-2.1' => ['Nano Banana 2.1 · Google', ['1K', '2K', '4K'], '±Rp795–1.695', '10–25 dtk'],
        'nano-banana-2' => ['Nano Banana 2 · Google', ['1K', '2K', '4K'], '±Rp1.500–3.345', '10–25 dtk'],
        'nano-banana' => ['Nano Banana · Google', ['1K'], '±Rp1.200', '8–15 dtk'],
        'nano-banana-pro' => ['Nano Banana Pro · Google', ['1K', '2K', '4K'], '±Rp3.000–5.355', '15–40 dtk'],
        'gpt-image-2.5' => ['GPT Image 2.5 Lite · OpenAI', ['1K', '2K', '4K'], '±Rp945', '30–150 dtk'],
        'gpt-image-2.5-flare' => ['GPT Image 2.5 Flare · OpenAI', ['1K', '2K'], '±Rp495–4.695', '10–45 dtk', ['quality' => true]],
        'gpt-image-2.5-sunburst' => ['GPT Image 2.5 Sunburst · OpenAI', ['1K', '2K'], '±Rp495–4.695', '15–150 dtk', ['quality' => true]],
        'gpt-image-2' => ['GPT Image 2 · OpenAI', ['1K', '2K'], '±Rp495–18.600', '20–240 dtk', ['quality' => true]],
        'gpt-image-1.5' => ['GPT Image 1.5 · OpenAI', ['1K'], '±Rp495–4.095', '8–60 dtk', ['quality' => true, 'aspects' => self::GPT1_ASPECTS]],
        'chatgpt-image' => ['ChatGPT Image · OpenAI', ['1K'], '±Rp555–4.245', '6–60 dtk', ['quality' => true]],
        'gpt-image-1' => ['GPT Image 1 · OpenAI', ['1K'], '±Rp495–5.055', '10–60 dtk', ['quality' => true, 'aspects' => self::GPT1_ASPECTS]],
        'gpt-image-1-mini' => ['GPT Image 1 Mini · OpenAI', ['1K'], '±Rp495–1.050', '8–40 dtk', ['quality' => true, 'aspects' => self::GPT1_ASPECTS]],
        'mai-image-2.6-flash' => ['MAI-Image 2.6 Flash · Microsoft', ['1K'], '±Rp1.305', '12–20 dtk'],
        'mai-image-2.6' => ['MAI-Image 2.6 · Microsoft', ['1K'], '±Rp2.550', '20–40 dtk'],
        'seedream-5' => ['Seedream 5.0 · ByteDance', ['2K', '3K'], '±Rp1.095', '30–40 dtk'],
        'seedream-5-pro' => ['Seedream 5.0 Pro · ByteDance', ['1K', '2K'], '±Rp2.550', '110–140 dtk'],
        'seedream-4.5' => ['Seedream 4.5 · ByteDance', ['2K', '4K'], '±Rp1.245', '8–20 dtk'],
        'seedream-4' => ['Seedream 4.0 · ByteDance', ['1K', '2K', '4K'], '±Rp1.095', '7–15 dtk'],
        'flux-2-pro' => ['FLUX.2 Pro', ['1K', '2K'], '±Rp1.005', '5–10 dtk'],
        'flux-2-flex' => ['FLUX.2 Flex', ['1K', '2K'], '±Rp1.905', '15–40 dtk'],
        'flux-2-max' => ['FLUX.2 Max', ['1K', '2K'], '±Rp2.250', '8–15 dtk'],
        'flux-1-dev' => ['FLUX.1 Dev · teks saja', ['1K'], '±Rp645', '5–8 dtk', ['no_reference' => true]],
        'flux-kontext-pro' => ['FLUX Kontext Pro · edit 1 gambar', ['1K'], '±Rp1.095', '6–12 dtk', ['needs_reference' => true]],
        'flux-kontext-max' => ['FLUX Kontext Max · edit 1 gambar', ['1K'], '±Rp2.145', '8–15 dtk', ['needs_reference' => true]],
    ];

    /** id => [nama, resolusi => harga per detik, detik [min, max] atau daftar, rasio, waktu] */
    private const VIDEOS = [
        'oxygen-1.0' => ['Oxygen 1.0 · paling hemat', ['480p' => 600, '768p' => 600], [4, 15], ['16:9', '9:16'], '1–3 mnt'],
        'minimax-h3' => ['MiniMax H3', ['768p' => 900], [4, 15], ['16:9', '9:16', '1:1', '4:3', '3:4', '21:9'], '3–10 mnt'],
        'wan-3.0' => ['Wan 3.0 · Alibaba', ['480p' => 1110, '720p' => 2205, '1080p' => 4425], [2, 30], ['16:9', '9:16', '1:1', '4:3', '3:4'], '2–3 mnt'],
        'wan-3.0-prime' => ['Wan 3.0 Prime · Alibaba', ['480p' => 1890, '720p' => 3795, '1080p' => 7590], [2, 30], ['16:9', '9:16', '1:1', '4:3', '3:4'], '1–2 mnt'],
        'wan-2.7' => ['Wan 2.7 · Alibaba', ['720p' => 2535, '1080p' => 3150], [2, 15], ['16:9', '9:16', '1:1', '4:3', '3:4'], '1–3 mnt'],
        'seedance-2.0-mini' => ['Seedance 2.0 Mini · ByteDance', ['480p' => 1110, '720p' => 2385], [4, 15], ['16:9', '9:16', '1:1', '4:3', '3:4', '21:9'], '1–2 mnt'],
        'seedance-2.0-fast' => ['Seedance 2.0 Fast · ByteDance', ['480p' => 1410, '720p' => 3030], [4, 15], ['16:9', '9:16', '1:1', '4:3', '3:4', '21:9'], '1–3 mnt'],
        'seedance-2.0' => ['Seedance 2.0 · ByteDance', ['480p' => 2220, '720p' => 4770, '1080p' => 11805], [4, 15], ['16:9', '9:16', '1:1', '4:3', '3:4', '21:9'], '2–3 mnt'],
        'seedance-2.5' => ['Seedance 2.5 · ByteDance', ['480p' => 3240, '720p' => 7305, '1080p' => 18450], [4, 30], ['16:9', '9:16', '1:1', '4:3', '3:4', '21:9'], '2–6 mnt'],
        'happyhorse-1.1' => ['HappyHorse 1.1 · Alibaba', ['720p' => 3120, '1080p' => 4725], [3, 15], ['16:9', '9:16', '1:1', '4:3', '3:4'], '1–3 mnt'],
        // Veo 3.1: harga per video (bukan per detik), durasi 4/6/8 detik; 1080p selalu 8 detik.
        'veo-3.1-fast' => ['Veo 3.1 Fast · Google', ['720p' => null, '1080p' => null], [4, 6, 8], ['16:9', '9:16'], '1–5 mnt'],
    ];

    /** id => [nama, harga per menit, maks karakter] */
    private const VOICES = [
        'voxcpm2' => ['VoxCPM2 · paling hemat', '±Rp795/menit', 5000],
        'dots-tts' => ['dots.tts · rednote', '±Rp795/menit', 5000],
        'openai-tts' => ['OpenAI TTS', '±Rp1.410/menit', 4000],
        'openai-tts-hd' => ['OpenAI TTS HD', '±Rp2.835/menit', 4000],
        'elevenlabs-flash' => ['Eleven Flash v2.5 · ElevenLabs', '±Rp2.100/menit', 5000],
        'elevenlabs-v4-turbo' => ['Eleven v4 Turbo · ElevenLabs', '±Rp2.100/menit', 5000],
        'elevenlabs-v4' => ['Eleven v4 · ElevenLabs', '±Rp4.200/menit', 5000],
        'elevenlabs-v2' => ['Eleven Multilingual v2 · ElevenLabs', '±Rp4.200/menit', 5000],
    ];

    /** @return array{image: list<array>, video: list<array>, audio: list<array>} */
    public function forProvider(AiProvider $provider): array
    {
        return Cache::remember('classai:media-catalog:'.$provider->getKey().':'.optional($provider->updated_at)->timestamp, self::TTL, function () use ($provider) {
            [$imageIds, $videoIds] = $this->liveMediaIds($provider);
            $voiceIds = $this->liveVoiceIds($provider);

            return [
                'image' => $this->filter($this->images(), $imageIds, fn ($id) => $this->image($id, [$id, ['1K'], null, null])),
                'video' => $this->filter($this->videos(), $videoIds, fn ($id) => $this->video($id, [$id, ['720p' => null], [4, 10], ['16:9', '9:16'], null])),
                'audio' => $this->filter($this->voices(), $voiceIds, fn ($id) => $this->voice($id, [$id, null, 4000])),
            ];
        });
    }

    /** Satu model dari katalog, atau null kalau tidak ada / tidak aktif. */
    public function find(AiProvider $provider, string $kind, string $id): ?array
    {
        return collect($this->forProvider($provider)[$kind] ?? [])->firstWhere('id', $id);
    }

    public function images(): array
    {
        return array_map(fn ($id) => $this->image($id, self::IMAGES[$id]), array_keys(self::IMAGES));
    }

    public function videos(): array
    {
        return array_map(fn ($id) => $this->video($id, self::VIDEOS[$id]), array_keys(self::VIDEOS));
    }

    public function voices(): array
    {
        return array_map(fn ($id) => $this->voice($id, self::VOICES[$id]), array_keys(self::VOICES));
    }

    private function image(string $id, array $row): array
    {
        $extra = $row[4] ?? [];

        return [
            'id' => $id, 'name' => $row[0], 'resolutions' => $row[1], 'price' => $row[2], 'time' => $row[3],
            'aspects' => $extra['aspects'] ?? array_keys(self::IMAGE_ASPECTS),
            'quality' => (bool) ($extra['quality'] ?? false),
            'reference' => ($extra['needs_reference'] ?? false) ? 'required' : (($extra['no_reference'] ?? false) ? 'none' : 'optional'),
        ];
    }

    private function video(string $id, array $row): array
    {
        $seconds = $row[2];
        // Dua angka = rentang [min, max]; lebih dari dua = hanya angka-angka itu (mis. Veo 4/6/8 detik).
        $list = count($seconds) > 2;

        return [
            'id' => $id, 'name' => $row[0], 'resolutions' => array_keys($row[1]),
            'price_per_second' => array_filter($row[1], fn ($v) => $v !== null),
            'seconds' => $list ? ['list' => array_values($seconds)] : ['min' => $seconds[0], 'max' => $seconds[1]],
            'aspects' => $row[3], 'time' => $row[4],
        ];
    }

    private function voice(string $id, array $row): array
    {
        return ['id' => $id, 'name' => $row[0], 'price' => $row[1], 'max_chars' => $row[2]];
    }

    /**
     * @param  list<string>|null  $live  ID yang aktif menurut API (null = API tidak terbaca, pakai semua).
     */
    private function filter(array $known, ?array $live, callable $make): array
    {
        if ($live === null || $live === []) {
            return $known;
        }

        $byId = collect($known)->keyBy('id');
        $result = $byId->only($live)->values()->all();

        foreach (array_diff($live, $byId->keys()->all()) as $id) {
            $result[] = $make($id);
        }

        return $result;
    }

    /** @return array{0: ?list<string>, 1: ?list<string>} ID gambar & video yang aktif menurut GET /v1/media/models. */
    private function liveMediaIds(AiProvider $provider): array
    {
        $json = $this->getJson($provider, '/media/models');

        if ($json === null) {
            return [null, null];
        }

        $images = [];
        $videos = [];

        $walk = function ($node, string $hint) use (&$walk, &$images, &$videos) {
            if (! is_array($node)) {
                return;
            }

            $id = $node['id'] ?? null;

            if (is_string($id) && preg_match(AiProvider::MODEL_PATTERN, $id)) {
                $type = strtolower((string) ($node['type'] ?? $node['kind'] ?? $node['category'] ?? $node['modality'] ?? $node['endpoint'] ?? $hint));

                if (str_contains($type, 'video') || isset(self::VIDEOS[$id]) || isset($node['price_per_second']) || isset($node['seconds_range'])) {
                    $videos[] = $id;
                } elseif (str_contains($type, 'image') || isset(self::IMAGES[$id])) {
                    $images[] = $id;
                }

                return;
            }

            foreach ($node as $key => $child) {
                $walk($child, is_string($key) ? $key : $hint);
            }
        };
        $walk($json, '');

        return [$images ? array_values(array_unique($images)) : null, $videos ? array_values(array_unique($videos)) : null];
    }

    /** @return ?list<string> mesin suara yang aktif menurut GET /v1/audio/voices. */
    private function liveVoiceIds(AiProvider $provider): ?array
    {
        $json = $this->getJson($provider, '/audio/voices');
        $items = is_array($json) ? ($json['data'] ?? $json['models'] ?? $json['engines'] ?? $json) : null;

        if (! is_array($items)) {
            return null;
        }

        $ids = [];

        foreach ($items as $key => $item) {
            $id = is_array($item) ? ($item['id'] ?? $item['model'] ?? null) : (is_string($key) ? $key : null);

            if (is_string($id) && preg_match(AiProvider::MODEL_PATTERN, $id)) {
                $ids[] = $id;
            }
        }

        return $ids ?: null;
    }

    private function getJson(AiProvider $provider, string $path): ?array
    {
        try {
            $response = Http::timeout(10)->acceptJson()->withToken($provider->api_key)->get($provider->baseUrl().$path);

            return $response->successful() && is_array($response->json()) ? $response->json() : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
