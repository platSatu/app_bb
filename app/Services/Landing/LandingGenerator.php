<?php

namespace App\Services\Landing;

use App\Models\LandingSection;
use App\Services\Ai\AiClient;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Minta AI menyusun isi section landing page. AI hanya mengisi teks terstruktur (bukan HTML),
 * lalu disaring: hanya jenis section & field yang dikenal, panjang dibatasi.
 */
class LandingGenerator
{
    private const TYPES = ['hero', 'features', 'about', 'pricing', 'faq', 'cta', 'contact'];

    /** @return array<int, array{type: string, data: array}> */
    public function generate(string $businessName, string $description): array
    {
        $fields = collect(self::TYPES)->mapWithKeys(fn ($type) => [$type => array_keys(LandingSection::TYPES[$type][1])])->all();

        $system = implode("\n", [
            'Kamu copywriter landing page UMKM Indonesia. Tulis dalam Bahasa Indonesia yang hangat, jelas, dan tidak berlebihan.',
            'Balas HANYA JSON valid: {"sections":[{"type":"...","title":"...", ...}]}.',
            'Jenis & field yang boleh dipakai: '.json_encode($fields),
            'Field "items": satu poin per baris, kolom dipisah " | " (features: Judul | keterangan; pricing: Nama | Harga | keterangan; faq: Pertanyaan | Jawaban).',
            'Urutan disarankan: hero, features, about, pricing, faq, cta, contact. Lewati section yang tidak relevan.',
            'JANGAN membuat testimoni, nama pelanggan, angka statistik, penghargaan, atau klaim yang tidak disebut pengguna.',
            'Harga, alamat, jam buka, dan nomor kontak hanya diisi kalau disebut pengguna; kalau tidak, kosongkan.',
            'Tanpa HTML, tanpa markdown, tanpa emoji berlebihan.',
        ]);

        $raw = AiClient::make()->complete($system, "Nama usaha: {$businessName}\nTentang usaha: {$description}");

        return $this->sanitize($raw);
    }

    private function sanitize(string $raw): array
    {
        // Sebagian model membungkus JSON dengan ```json ... ```.
        $json = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', $raw)), true);

        if (! is_array($json['sections'] ?? null)) {
            throw new RuntimeException('Hasil AI belum bisa dibaca. Coba klik Buat dengan AI sekali lagi.');
        }

        return collect($json['sections'])
            ->filter(fn ($section) => is_array($section) && in_array($section['type'] ?? null, self::TYPES, true))
            ->take(10)
            ->map(function (array $section) {
                $data = [];

                foreach (LandingSection::TYPES[$section['type']][1] as $field => [, $max]) {
                    $value = $section[$field] ?? '';
                    $value = is_array($value) ? implode("\n", array_map(fn ($v) => is_array($v) ? implode(' | ', $v) : (string) $v, $value)) : (string) $value;
                    $data[$field] = Str::limit(trim(strip_tags($value)), $max, '');
                }

                return ['type' => $section['type'], 'data' => $data];
            })
            ->values()
            ->all();
    }
}
