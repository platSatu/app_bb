<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserBranch extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /** Notifikasi WhatsApp yang bisa dinyalakan per branch (bawaan mati), isinya sama dengan email. */
    public const WA_NOTIFY = [
        'invoice' => ['Invoice', 'Tagihan baru, pengingat jatuh tempo & bukti lunas ke customer.'],
        'ticket' => ['Tiket', 'E-ticket ke pembeli setelah pembayaran berhasil.'],
        'schedule' => ['Jadwal', 'Pengingat les sehari sebelumnya (H-1) ke murid / orang tua.'],
    ];

    // user_id, company_id & slug diisi sistem, tidak pernah dari form. wa_notify diubah lewat form sakelar sendiri.
    protected $fillable = ['name', 'phone', 'handphone', 'address', 'city', 'description', 'status'];

    protected function casts(): array
    {
        return ['wa_notify' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (UserBranch $branch) {
            $branch->slug ??= static::uniqueSlug($branch->company_id, $branch->name);
        });
    }

    public static function uniqueSlug(string $companyId, string $name): string
    {
        $base = Str::slug($name) ?: 'branch';
        $slug = $base;

        while (static::where('company_id', $companyId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(UserCompany::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Sakelar notifikasi WhatsApp $kind nyala dan paket WhatsApp branch ini masih aktif. */
    public function waNotifies(string $kind): bool
    {
        return ! empty($this->wa_notify[$kind]) && app(\App\Services\Subscription\AppAccess::class)->branchActive($this->getKey(), 'whatsapp');
    }

    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim((string) $keyword);

        return $query->when($keyword !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
            ->where('name', 'like', "%{$keyword}%")
            ->orWhere('city', 'like', "%{$keyword}%")));
    }
}
