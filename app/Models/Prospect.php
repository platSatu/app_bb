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

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-light text-dark';
    }
}
