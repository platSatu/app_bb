<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LandingSection extends Model
{
    use HasActiveStatus, HasUuids;

    /*
     * Jenis section & field teksnya: field => [label, maks karakter, multiline].
     * Field "items" ditulis satu baris per poin, kolom dipisah tanda |.
     * Gambar (image / images) diatur terpisah karena berupa upload.
     */
    public const TYPES = [
        'hero' => ['Hero', [
            'title' => ['Judul besar', 120, false],
            'subtitle' => ['Subjudul', 300, true],
            'button_label' => ['Teks tombol', 40, false],
        ], 'image'],
        'features' => ['Keunggulan', [
            'title' => ['Judul', 120, false],
            'items' => ['Poin (Judul | keterangan), satu per baris', 2000, true],
        ], null],
        'about' => ['Tentang', [
            'title' => ['Judul', 120, false],
            'body' => ['Isi', 3000, true],
        ], 'image'],
        'gallery' => ['Galeri', [
            'title' => ['Judul', 120, false],
        ], 'images'],
        'pricing' => ['Harga / Paket', [
            'title' => ['Judul', 120, false],
            'items' => ['Paket (Nama | Harga | keterangan), satu per baris', 2000, true],
        ], null],
        'faq' => ['FAQ', [
            'title' => ['Judul', 120, false],
            'items' => ['Tanya-jawab (Pertanyaan | Jawaban), satu per baris', 4000, true],
        ], null],
        'video' => ['Video YouTube', [
            'title' => ['Judul', 120, false],
            'youtube_url' => ['Link YouTube', 255, false],
        ], null],
        'cta' => ['Ajakan (CTA)', [
            'title' => ['Judul', 120, false],
            'body' => ['Kalimat ajakan', 500, true],
            'button_label' => ['Teks tombol', 40, false],
        ], null],
        'contact' => ['Kontak', [
            'title' => ['Judul', 120, false],
            'address' => ['Alamat', 500, true],
            'hours' => ['Jam buka', 200, true],
            'maps_url' => ['Link Google Maps', 500, false],
            'whatsapp' => ['No. WhatsApp (untuk tombol chat)', 20, false],
        ], null],
    ];

    // Section yang punya tombol: tujuan dipilih dari page satu branch, atau URL luar.
    public const WITH_BUTTON = ['hero', 'cta'];

    public const MAX_GALLERY = 12;

    protected $fillable = ['type', 'data', 'status'];

    protected function casts(): array
    {
        return ['data' => 'array', 'sort_order' => 'integer'];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /** "Judul | keterangan" per baris -> [[Judul, keterangan], ...] */
    public function rows(string $key = 'items'): array
    {
        return collect(preg_split('/\R/', (string) $this->get($key)))
            ->map(fn ($line) => array_map('trim', explode('|', $line)))
            ->filter(fn ($cols) => $cols[0] !== '')
            ->values()
            ->all();
    }

    public function youtubeId(): ?string
    {
        preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})~', (string) $this->get('youtube_url'), $m);

        return $m[1] ?? null;
    }
}
