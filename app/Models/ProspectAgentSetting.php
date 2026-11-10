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

    protected $fillable = ['is_active', 'segments', 'cities', 'daily_quota', 'run_at', 'report_email', 'updated_by'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'segments' => 'array',
            'cities' => 'array',
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

    public function logRun(array $run): void
    {
        $this->forceFill(['runs' => array_slice([$run + ['at' => now()->toIso8601String()], ...($this->runs ?? [])], 0, self::MAX_RUNS)])->save();
    }
}
