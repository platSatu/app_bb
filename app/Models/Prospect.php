<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Calon pelanggan bizbos dari Tools › Open Places (Tools › Prospek). */
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
        'source', 'external_id', 'name', 'category', 'address', 'city', 'province', 'postal_code', 'phone', 'whatsapp',
        'email', 'website', 'rating', 'rating_count', 'opening_hours', 'maps_url', 'lat', 'lon', 'is_closed',
        'status', 'notes', 'contacted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['rating' => 'float', 'rating_count' => 'integer', 'lat' => 'float', 'lon' => 'float', 'is_closed' => 'boolean', 'contacted_at' => 'datetime'];
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
