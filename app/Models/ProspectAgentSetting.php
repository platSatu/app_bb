<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Pengaturan & riwayat Agent Prospek (satu baris). Lihat App\Services\Marketing\ProspectAgent. */
class ProspectAgentSetting extends Model
{
    use HasUuids;

    /** Bawaan: usaha dengan murid/anggota tetap, jadwal rutin & tagihan bulanan (cocok Jadwal + Tagihan + WA bizbos). */
    public const DEFAULT_SEGMENTS = [
        ['name' => 'Kursus musik', 'keywords' => ['kursus musik', 'les piano', 'les gitar']],
        ['name' => 'Kursus bahasa', 'keywords' => ['kursus bahasa', 'kursus bahasa inggris', 'kursus mandarin']],
        ['name' => 'Bimbel', 'keywords' => ['bimbel', 'bimbingan belajar']],
        ['name' => 'Studio & sanggar', 'keywords' => ['studio yoga', 'pilates', 'sanggar tari', 'studio dance']],
    ];

    public const DEFAULT_CITIES = [
        'Jakarta Selatan', 'Jakarta Barat', 'Jakarta Timur', 'Jakarta Utara', 'Jakarta Pusat',
        'Bekasi', 'Tangerang', 'Tangerang Selatan', 'Depok', 'Bogor',
    ];

    public const MAX_RUNS = 50;

    /** Template email pembuka kalau segmen belum punya template sendiri. {nama} {kota} {segmen} diganti per prospek. */
    public const DEFAULT_TEMPLATE = [
        'subject' => 'Jadwal kelas & tagihan {nama} jadi lebih mudah',
        'body' => "# Urus jadwal & tagihan murid tanpa repot\n\nHalo {nama},\n\nPerkenalkan, kami dari bizbos, aplikasi untuk usaha {segmen} di "
            ."Indonesia. Dengan bizbos Anda bisa mengatur jadwal kelas, menagih biaya bulanan lewat link bayar (QRIS, virtual account, "
            ."e-wallet), dan mengirim pengingat otomatis lewat WhatsApp, semuanya dalam satu aplikasi.\n\n[tombol: Coba bizbos gratis | https://app.bizbos.id]"
            ."\n\nKalau berminat, cukup balas email ini. Kami bantu demo singkat 15 menit secara online, gratis.\n\nSalam hangat,\nTim bizbos",
    ];

    /** "Segmen" kontak hasil import Excel: punya template sendiri, tidak dipakai untuk mencari tempat. */
    public const IMPORT_SEGMENT = 'Kontak import';

    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    protected $fillable = [
        'is_active', 'segments', 'cities', 'templates', 'min_score', 'daily_quota', 'run_at', 'report_email', 'updated_by',
        'send_enabled', 'send_days', 'send_start', 'send_end', 'send_per_hour', 'send_per_day', 'auto_approve_import',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'segments' => 'array',
            'cities' => 'array',
            'templates' => 'array',
            'min_score' => 'integer',
            'send_enabled' => 'boolean',
            'send_days' => 'array',
            'send_start' => 'integer',
            'send_end' => 'integer',
            'send_per_hour' => 'integer',
            'send_per_day' => 'integer',
            'auto_approve_import' => 'boolean',
            'runs' => 'array',
            'daily_quota' => 'integer',
            'cursor' => 'integer',
            'last_run_on' => 'date',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([
            'segments' => self::DEFAULT_SEGMENTS,
            'cities' => self::DEFAULT_CITIES,
        ])->refresh();
    }

    /**
     * Daftar giliran segmen × kata kunci × kota, dikelompokkan per kota (satu kota disisir semua segmennya dulu).
     *
     * @return array<int, array{segment: string, keyword: string, city: string}>
     */
    public function tasks(): array
    {
        $tasks = [];

        foreach ($this->cities ?? [] as $city) {
            foreach ($this->segments ?? [] as $segment) {
                foreach ($segment['keywords'] ?? [] as $keyword) {
                    $tasks[] = ['segment' => $segment['name'], 'keyword' => $keyword, 'city' => $city];
                }
            }
        }

        return $tasks;
    }

    /** Segmen dalam format isian: satu baris "Nama segmen: kata kunci 1, kata kunci 2". */
    public function segmentsText(): string
    {
        return collect($this->segments ?? [])->map(fn ($s) => $s['name'].': '.implode(', ', $s['keywords'] ?? []))->implode("\n");
    }

    /** Contoh awal untuk kontak import (pembeli tiket yang setuju menerima promo). Wajib disimpan dulu sebelum kirim otomatis. */
    public const DEFAULT_IMPORT_TEMPLATE = [
        'subject' => 'Halo {nama}, ada kabar baru untuk Anda',
        'body' => "# Ada kabar baru untuk Anda\n\nHalo {nama},\n\nTerima kasih sudah pernah membeli tiket bersama kami. Sesuai pilihan Anda "
            ."untuk menerima info promo, kami ingin berbagi kabar terbaru:\n\n(Tulis isi promo di sini)\n\n[tombol: Lihat promo | https://app.bizbos.id]"
            ."\n\nSampai jumpa di acara berikutnya!\n\nSalam hangat,\nTim bizbos",
    ];

    /** @return array{subject: string, body: string} */
    public function template(?string $segment): array
    {
        return ($this->templates ?? [])[(string) $segment]
            ?? ($segment === self::IMPORT_SEGMENT ? self::DEFAULT_IMPORT_TEMPLATE : self::DEFAULT_TEMPLATE);
    }

    /** Nama segmen yang punya template: segmen pencarian + Kontak import. */
    public function templateSegments(): array
    {
        return [...collect($this->segments ?? [])->pluck('name')->all(), self::IMPORT_SEGMENT];
    }

    /** Hari kirim (1 = Senin). Belum diatur = Senin-Jumat. */
    public function sendDays(): array
    {
        return array_map('intval', $this->send_days ?? [1, 2, 3, 4, 5]);
    }

    /** Sekarang boleh mengirim: tombol aktif, hari dicentang, di antara jam mulai & selesai (WIB). */
    public function sendOpen(): bool
    {
        return $this->send_enabled && in_array(now()->dayOfWeekIso, $this->sendDays(), true)
            && now()->hour >= $this->send_start && now()->hour < $this->send_end;
    }

    public function logRun(array $run): void
    {
        $this->forceFill(['runs' => array_slice([$run + ['at' => now()->toIso8601String()], ...($this->runs ?? [])], 0, self::MAX_RUNS)])->save();
    }
}
