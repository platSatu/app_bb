<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Aturan komisi referral (1 baris): pengajak dapat persen dari setiap pembelian paket orang yang diajak,
 * selama active_months sejak orang itu daftar. Komisi ditahan hold_days sebelum cair ke saldo.
 */
class ReferralSetting extends Model
{
    use HasUuids;

    protected $fillable = ['is_active', 'default_percent', 'hold_days', 'active_months'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'default_percent' => 'integer', 'hold_days' => 'integer', 'active_months' => 'integer'];
    }

    /** Baris pengaturan satu-satunya. Baris baru dibaca ulang supaya default database ikut terisi. */
    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create(['is_active' => false])->refresh();
    }
}
