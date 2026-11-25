<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Gambar, suara & video dari Classai Router (dokumentasi: router.classai.id/dashboard/docs).
 * - Gambar: POST /v1/images/generations (sinkron 5 dtk–4 mnt), hasil base64 atau URL.
 * - Suara:  POST /v1/audio/speech, hasil file mp3/wav; GET /v1/audio/voices?model= untuk daftar suara.
 * - Video:  POST /v1/videos (tugas), GET /v1/videos/{id} sampai completed, lalu GET /v1/videos/{id}/content.
 * File hasil disimpan di disk "local" (storage/app/private), tidak bisa dibuka publik.
 */
class ClassaiMedia
{
    public const DISK = 'local';

    private const MAX_IMAGE_BYTES = 30 * 1024 * 1024;

    private const MAX_AUDIO_BYTES = 50 * 1024 * 1024;

    private const MAX_VIDEO_BYTES = 300 * 1024 * 1024;

    private const IMAGE_MIMES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    private const AUDIO_MIMES = ['audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/wave' => 'wav'];

    /**
     * @param  array{prompt: string, aspect_ratio?: string, resolution?: ?string, quality?: ?string, n?: int, reference?: ?string}  $o
     * @return array{files: list<array{bytes: string, mime: string, ext: string}>, cost_micro_rp: ?int}
     */
    public function image(AiProvider $provider, string $model, array $o): array
    {
        $response = $this->post($provider, '/images/generations', array_filter([
            'model' => $model,
            'prompt' => $o['prompt'],
            'aspect_ratio' => $o['aspect_ratio'] ?? null,
            'resolution' => $o['resolution'] ?? null,
            'quality' => $o['quality'] ?? null,
            'n' => $o['n'] ?? 1,
            'image' => filled($o['reference'] ?? null) ? [$this->dataUri($o['reference'])] : null,
            'response_format' => 'b64_json',
        ], fn ($v) => $v !== null), 300);

        $files = [];

        foreach ((array) $response->json('data') as $item) {
            $bytes = null;

            if (is_string($item['b64_json'] ?? null)) {
                $bytes = base64_decode($item['b64_json'], true) ?: null;
            } elseif (is_string($item['url'] ?? null) && str_starts_with($item['url'], 'https://')) {
                $bytes = $this->download($item['url'], self::MAX_IMAGE_BYTES);
            }

            if ($bytes !== null) {
                $files[] = $this->checked($bytes, self::IMAGE_MIMES, self::MAX_IMAGE_BYTES, 'gambar');
            }
        }

        if ($files === []) {
            throw new RuntimeException('AI tidak mengirim gambar. Coba ubah deskripsinya atau pilih model lain.');
        }

        return ['files' => $files, 'cost_micro_rp' => $this->cost($response)];
    }

    /**
     * @param  array{text: string, voice: string, style?: ?string, speed?: ?float}  $o
     * @return array{files: list<array{bytes: string, mime: string, ext: string}>, cost_micro_rp: ?int}
     */
    public function speech(AiProvider $provider, string $model, array $o): array
    {
        $response = $this->post($provider, '/audio/speech', array_filter([
            'model' => $model,
            'input' => $o['text'],
            'voice' => $o['voice'],
            'style' => $o['style'] ?? null,
            'speed' => $o['speed'] ?? null,
            'response_format' => 'mp3',
        ], fn ($v) => $v !== null), 300);

        return ['files' => [$this->checked($response->body(), self::AUDIO_MIMES, self::MAX_AUDIO_BYTES, 'suara')], 'cost_micro_rp' => $this->cost($response)];
    }

    /**
     * Daftar suara satu mesin (GET /v1/audio/voices?model=). Bentuk balasan belum dirinci di dokumentasi,
     * jadi dibaca seadanya: id, nama, contoh suara (https) dan tanda kloning.
     *
     * @return list<array{id: string, name: string, sample: ?string, cloned: bool}>
     */
    public function voices(AiProvider $provider, string $model): array
    {
        $json = $this->get($provider, '/audio/voices', ['model' => $model])->json();
        $items = is_array($json) ? ($json['data'] ?? $json['voices'] ?? $json) : [];
        $voices = [];

        foreach ((array) $items as $key => $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = $item['id'] ?? $item['voice_id'] ?? $item['voice'] ?? (is_string($key) ? $key : null);

            if (! is_string($id) || ! preg_match(AiProvider::MODEL_PATTERN, $id)) {
                continue;
            }

            $cloned = (bool) ($item['cloned'] ?? false);
            $sample = $item['sample'] ?? $item['sample_url'] ?? $item['preview_url'] ?? $item['preview'] ?? null;
            $label = collect([$item['gender'] ?? null, $item['language'] ?? $item['lang'] ?? null, $item['description'] ?? null])
                ->filter(fn ($v) => is_string($v) && $v !== '')->map(fn ($v) => mb_substr(strip_tags($v), 0, 40))->implode(' · ');
            $name = is_string($item['name'] ?? null) ? mb_substr(strip_tags($item['name']), 0, 60) : $id;

            $voices[] = [
                'id' => $cloned && ! str_starts_with($id, 'clone:') ? 'clone:'.$id : $id,
                'name' => $name.($label !== '' ? ' ('.$label.')' : '').($cloned ? ' · suara kloning Anda' : ''),
                'sample' => is_string($sample) && str_starts_with($sample, 'https://') ? $sample : null,
                'cloned' => $cloned,
            ];
        }

        return $voices;
    }

    /**
     * @param  array{prompt: string, aspect_ratio?: ?string, resolution?: ?string, seconds?: ?int, reference?: ?string}  $o
     * @return string ID tugas video di Classai
     */
    public function startVideo(AiProvider $provider, string $model, array $o): string
    {
        $response = $this->post($provider, '/videos', array_filter([
            'model' => $model,
            'prompt' => $o['prompt'],
            'aspect_ratio' => $o['aspect_ratio'] ?? null,
            'resolution' => $o['resolution'] ?? null,
            'seconds' => isset($o['seconds']) ? (string) $o['seconds'] : null,
            'input_reference' => filled($o['reference'] ?? null) ? $this->dataUri($o['reference']) : null,
        ], fn ($v) => $v !== null), 60);

        $id = $response->json('id');

        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $id)) {
            throw new RuntimeException('Classai tidak mengembalikan ID tugas video. Coba lagi sebentar lagi.');
        }

        return $id;
    }

    /** @return array{status: string, error: ?string} status: in_progress | completed | failed */
    public function videoStatus(AiProvider $provider, string $id): array
    {
        $json = $this->get($provider, '/videos/'.rawurlencode($id))->json();
        $status = is_string($json['status'] ?? null) ? $json['status'] : 'in_progress';
        $error = $json['error']['message'] ?? null;

        return ['status' => $status, 'error' => is_string($error) ? mb_substr(strip_tags($error), 0, 300) : null];
    }

    /** @return array{bytes: string, mime: string, ext: string} */
    public function downloadVideo(AiProvider $provider, string $id): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aivid');

        try {
            $response = Http::timeout(300)->connectTimeout(15)->withToken($provider->api_key)
                ->withOptions(['sink' => $tmp])->get($provider->baseUrl().'/videos/'.rawurlencode($id).'/content');
        } catch (Throwable $e) {
            @unlink($tmp);
            report($e);

            throw new RuntimeException('Video sudah jadi, tapi gagal diunduh dari Classai. Akan dicoba lagi.');
        }

        try {
            if ($response->failed()) {
                throw new RuntimeException(AiClient::errorMessage($response->status()));
            }

            if (filesize($tmp) > self::MAX_VIDEO_BYTES) {
                throw new RuntimeException('Ukuran video terlalu besar untuk disimpan.');
            }

            return $this->checked((string) file_get_contents($tmp), ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'], self::MAX_VIDEO_BYTES, 'video');
        } finally {
            @unlink($tmp);
        }
    }

    /** Simpan satu file hasil; kembalikan data untuk kolom ai_messages.media. */
    public function store(string $conversationId, array $file): array
    {
        $path = 'ai-media/'.$conversationId.'/'.Str::uuid().'.'.$file['ext'];
        Storage::disk(self::DISK)->put($path, $file['bytes']);

        return ['path' => $path, 'mime' => $file['mime'], 'size' => strlen($file['bytes'])];
    }

    private function post(AiProvider $provider, string $path, array $body, int $timeout): Response
    {
        try {
            $response = Http::timeout($timeout)->connectTimeout(15)->withToken($provider->api_key)->post($provider->baseUrl().$path, $body);
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('Classai Router sedang tidak bisa dihubungi atau terlalu lama menjawab. Coba lagi sebentar lagi.');
        }

        return $this->ok($response, $path);
    }

    private function get(AiProvider $provider, string $path, array $query = []): Response
    {
        try {
            $response = Http::timeout(20)->acceptJson()->withToken($provider->api_key)->get($provider->baseUrl().$path, $query);
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('Classai Router sedang tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        return $this->ok($response, $path);
    }

    private function ok(Response $response, string $path): Response
    {
        if ($response->successful()) {
            return $response;
        }

        report(new RuntimeException('Classai '.$path.' HTTP '.$response->status().': '.mb_substr($response->body(), 0, 500)));

        // 400 biasanya karena isi permintaan (mis. deskripsi ditolak filter keamanan): pesan dari Classai ditampilkan, dibersihkan.
        $detail = $response->json('error.message');

        throw new RuntimeException($response->status() === 400 && is_string($detail)
            ? 'Permintaan ditolak Classai: '.mb_substr(strip_tags($detail), 0, 200)
            : AiClient::errorMessage($response->status()));
    }

    private function download(string $url, int $max): ?string
    {
        try {
            $response = Http::timeout(60)->get($url);

            return $response->successful() && strlen($response->body()) <= $max ? $response->body() : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Pastikan isi file benar-benar tipe yang diharapkan (dicek dari isinya, bukan dari keterangan pengirim). */
    private function checked(string $bytes, array $allowed, int $max, string $label): array
    {
        if ($bytes === '' || strlen($bytes) > $max) {
            throw new RuntimeException("File {$label} dari Classai kosong atau terlalu besar.");
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';

        // finfo kadang membaca mp3/wav sebagai application/octet-stream; kenali dari tanda di awal file.
        if ($label === 'suara' && ! isset($allowed[$mime])) {
            if (str_starts_with($bytes, 'ID3') || preg_match('/^\xFF[\xE0-\xFF]/', $bytes)) {
                $mime = 'audio/mpeg';
            } elseif (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WAVE') {
                $mime = 'audio/wav';
            }
        }

        if (! isset($allowed[$mime])) {
            report(new RuntimeException("Classai {$label}: tipe file tidak dikenal ({$mime})."));

            throw new RuntimeException("File {$label} dari Classai tidak bisa dibaca.");
        }

        return ['bytes' => $bytes, 'mime' => $mime === 'audio/mp3' ? 'audio/mpeg' : $mime, 'ext' => $allowed[$mime]];
    }

    /** Gambar referensi (disimpan di disk local) -> data URI untuk dikirim ke Classai. */
    private function dataUri(string $path): string
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            throw new RuntimeException('Gambar referensi tidak ditemukan. Unggah ulang lalu coba lagi.');
        }

        return 'data:'.($disk->mimeType($path) ?: 'image/png').';base64,'.base64_encode($disk->get($path));
    }

    private function cost(Response $response): ?int
    {
        $value = $response->header('x-classai-cost-micro-rp');

        return is_numeric($value) ? (int) $value : null;
    }
}
