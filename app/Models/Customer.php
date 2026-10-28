<?php

namespace App\Models;

use App\Support\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer milik satu branch DAN satu aplikasi (app_key). Bukan akun login.
 * Orang yang sama di Pages dan Invoice = dua baris terpisah.
 */
class Customer extends Model
{
    use HasUuids;

    // Pengunjung live chat bizbos.id: milik platform (tanpa company/branch), hanya terlihat superadmin.
    public const APP_WEBSITE = 'website';

    public const APPS = [
        'events' => 'Event',
        'pages' => 'Pages',
        'schedules' => 'Schedules',
        'invoices' => 'Invoice',
        'whatsapp' => 'WhatsApp',
        self::APP_WEBSITE => 'Website (Live Chat)',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(UserBranch::class, 'branch_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(UserCompany::class, 'company_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_group_customer')->withPivot('created_at');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(PageSubmission::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeForApp(Builder $query, string $app): Builder
    {
        return $query->where($this->qualifyColumn('app_key'), $app);
    }

    public function appLabel(): string
    {
        return self::APPS[$this->app_key] ?? $this->app_key;
    }

    /** Owner: hanya kontak perusahaannya. Tim platform: semua, sesuai izin menu superadmin.customers. */
    public function scopeAccessibleBy(Builder $query, User $user, string $ability = Menu::ABILITY_VIEW): Builder
    {
        if (! $user->isPlatformTeam()) {
            return $query->whereIn('company_id', UserCompany::where('user_id', $user->getKey())->select('id'));
        }

        return $user->canAccess('superadmin.customers', $ability) ? $query : $query->whereRaw('1 = 0');
    }

    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim((string) $keyword);
        $digits = preg_replace('/^(62|0)/', '', preg_replace('/\D/', '', $keyword));

        return $query->when($keyword !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
            ->where('name', 'like', "%{$keyword}%")
            ->orWhere('email', 'like', "%{$keyword}%")
            ->when(strlen($digits) >= 3, fn (Builder $p) => $p->orWhere('phone', 'like', "%{$digits}%"))));
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->phone ? '+'.$this->phone : ($this->email ?: 'Tanpa nama'));
    }
}
