<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kode referral milik satu user. commission_percent diatur superadmin (NULL = persen default);
 * share_percent diatur pemilik kode: bagian komisinya yang diberikan ke orang yang diajak sebagai potongan harga.
 */
class ReferralCode extends Model
{
    use HasUuids;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['commission_percent' => 'integer', 'share_percent' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function link(): string
    {
        return route('register', ['ref' => $this->code]);
    }

    /** Total persen milik pengajak ini (sebelum dibagi ke orang yang diajak). */
    public function commissionPercent(ReferralSetting $setting): int
    {
        return $this->commission_percent ?? $setting->default_percent;
    }

    /** Bagian untuk orang yang diajak; tidak pernah melebihi total persen (mis. superadmin menurunkan persennya). */
    public function sharePercent(ReferralSetting $setting): int
    {
        return min((int) $this->share_percent, $this->commissionPercent($setting));
    }
}
