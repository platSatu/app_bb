<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Calon pelanggan bizbos dari Tools › Open Places / Agent Prospek (Tools › Prospek). */
class Prospect extends Model
{
    use HasUuids;

    public const STATUSES = [
        'new' => ['Baru', 'bg-primary-subtle text-primary'],
        'contacted' => ['Dihubungi', 'bg-info-subtle text-info'],
        'interested' => ['Tertarik', 'bg-warning-subtle text-warning'],
        'customer' => ['Jadi Pelanggan', 'bg-success-subtle text-success'],
        'rejected' => ['Tidak Tertarik', 'bg-secondary-subtle text-secondary'],
    ];

    /** Email pembuka dari Agent Prospek (tab Persetujuan). Diubah lewat forceFill, tidak lewat $fillable. */
    public const OUTREACH = [
        'draft' => ['Email: draf', 'bg-warning-subtle text-warning'],
        'approved' => ['Email: disetujui', 'bg-info-subtle text-info'],
        'sent' => ['Email: terkirim', 'bg-success-subtle text-success'],
        'skipped' => ['Email: dilewati', 'bg-secondary-subtle text-secondary'],
        'unsubscribed' => ['Email: berhenti', 'bg-danger-subtle text-danger'],
    ];

    /** Batas baris per file import (pemakaian memori saat membaca Excel). */
    public const IMPORT_MAX_ROWS = 5000;

    protected $fillable = [
        'source', 'external_id', 'name', 'category', 'segment', 'score', 'score_reason', 'address', 'city', 'province', 'postal_code', 'phone', 'whatsapp',
        'email', 'website', 'rating', 'rating_count', 'opening_hours', 'maps_url', 'lat', 'lon', 'is_closed',
        'status', 'notes', 'contacted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['rating' => 'float', 'rating_count' => 'integer', 'lat' => 'float', 'lon' => 'float', 'is_closed' => 'boolean', 'contacted_at' => 'datetime'];
    }

    /**
     * Simpan tempat dari Open Places (halaman Open Places & Agent Prospek). Yang sudah ada (id tempat atau nomor
     * telepon sama) dilewati. $rows sudah bersih (lihat GooglePlacesController::selected() & ProspectAgent).
     *
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public static function saveFromPlaces(iterable $rows, string $source, ?string $userId): int
    {
        $saved = 0;

        foreach ($rows as $row) {
            $phone = (string) ($row['phone'] ?? '');

            if (($row['id'] ?? '') === '' || static::where('external_id', 'open:'.$row['id'])
                ->when($phone !== '', fn ($q) => $q->orWhere('phone', $phone))->exists()) {
                continue;
            }

            static::create([
                'source' => $source,
                'external_id' => 'open:'.$row['id'],
                'is_closed' => ! empty($row['closed']),
                'created_by' => $userId,
            ] + array_map(fn ($value) => $value === '' ? null : $value, array_intersect_key($row, array_flip([
                'name', 'category', 'segment', 'score', 'score_reason', 'address', 'city', 'province', 'postal_code',
                'phone', 'whatsapp', 'email', 'website', 'maps_url', 'lat', 'lon',
            ]))));
            $saved++;
        }

        return $saved;
    }

    /**
     * Kontak dari file Excel/CSV (Tools › Prospek › Import). Email wajib & valid; email yang sudah ada di Prospek
     * (huruf besar/kecil diabaikan) dilewati, jadi tidak ada kontak dobel.
     *
     * @param  array<int, array{name: ?string, phone: ?string, email: ?string, city?: ?string}>  $rows
     * @return array{saved: int, skipped: int}
     */
    public static function importRows(array $rows, ?string $userId): array
    {
        $known = static::whereNotNull('email')->pluck('email')->mapWithKeys(fn ($e) => [strtolower($e) => true])->all();
        $saved = 0;
        $skipped = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            $insert = [];

            foreach ($chunk as $row) {
                $email = strtolower(trim((string) ($row['email'] ?? '')));

                if (strlen($email) > 150 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || isset($known[$email])) {
                    $skipped++;

                    continue;
                }

                $known[$email] = true;
                // 0812.., 62812.. atau 812.. (angka 0 di depan hilang di Excel) -> telepon 0812.., WA 62812..
                $digits = preg_replace('/^(62|0)/', '', preg_replace('/\D/', '', (string) ($row['phone'] ?? '')));
                $phone = strlen($digits) >= 8 ? mb_substr($digits, 0, 17) : '';
                $insert[] = [
                    'id' => (string) \Illuminate\Support\Str::uuid7(), 'source' => 'import', 'segment' => ProspectAgentSetting::IMPORT_SEGMENT,
                    'name' => mb_substr(trim((string) ($row['name'] ?? '')) ?: strstr($email, '@', true), 0, 200), 'email' => $email,
                    'phone' => $phone !== '' ? '0'.$phone : null, 'whatsapp' => $phone !== '' ? '62'.$phone : null,
                    'city' => mb_substr(trim((string) ($row['city'] ?? '')), 0, 150) ?: null,
                    'status' => 'new', 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
                ];
            }

            static::insert($insert);
            $saved += count($insert);
        }

        return ['saved' => $saved, 'skipped' => $skipped];
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-light text-dark';
    }
}
