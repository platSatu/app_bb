<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\Menu;
use Laravel\Sanctum\HasApiTokens;

/**
 * Daftar sendiri (form register / link pendaftaran portal) wajib verifikasi email.
 * Akun yang dibuat dari dalam (superadmin, akun customer, Google) langsung terisi email_verified_at.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_SUPERADMIN = 'superadmin';
    public const TYPE_STAFF = 'staff';
    public const TYPE_USER = 'user';

    public const TYPE_LABELS = [
        self::TYPE_SUPERADMIN => 'Superadmin',
        self::TYPE_STAFF => 'Staff',
        self::TYPE_USER => 'User',
    ];

    private ?array $platformPermissionCache = null;

    private ?array $portalRolesCache = null;

    public const INACTIVE_MESSAGE = 'Akun Anda sedang tidak aktif. Silakan hubungi admin kami.';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'handphone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Lewat antrean; gagal masuk antrean pun tidak boleh menggagalkan pendaftaran (user bisa kirim ulang). */
    public function sendEmailVerificationNotification(): void
    {
        try {
            $this->notify(new \App\Notifications\QueuedVerifyEmail());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new \App\Notifications\QueuedResetPassword($token));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuperadmin(): bool
    {
        return $this->user_type === self::TYPE_SUPERADMIN;
    }

    public function isStaff(): bool
    {
        return $this->user_type === self::TYPE_STAFF;
    }

    public function isPlatformTeam(): bool
    {
        return $this->isSuperadmin() || $this->isStaff();
    }

    public function scheduleStudents(): HasMany
    {
        return $this->hasMany(ScheduleStudent::class);
    }

    public function scheduleTeachers(): HasMany
    {
        return $this->hasMany(ScheduleTeacher::class);
    }

    /** Peran selain owner, dibaca dari data: murid/orang tua, pengajar, dan/atau pembayar tagihan (bisa lintas perusahaan). */
    public function portalRoles(): array
    {
        return $this->portalRolesCache ??= [
            'student' => $this->scheduleStudents()->exists(),
            'teacher' => $this->scheduleTeachers()->exists(),
            // Pembayar: ada tagihan (dari dashboard penagih) yang ditujukan ke email akun ini.
            'payer' => \App\Services\Invoice\PayerInvoices::has($this),
        ];
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function referralCode(): HasOne
    {
        return $this->hasOne(ReferralCode::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function company(): HasOne
    {
        return $this->hasOne(UserCompany::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function canAccess(string $key, string $ability = Menu::ABILITY_VIEW): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        $item = Menu::find($key);

        // Menu aplikasi milik user sendiri (data selalu di-scope ke perusahaannya).
        // Owner: hanya terbuka kalau paket layanannya aktif di minimal satu branch miliknya.
        if ($item['owner_app'] ?? false) {
            return $this->isPlatformTeam() || empty($item['app'])
                || app(\App\Services\Subscription\AppAccess::class)->ownerHas($this, $item['app']);
        }

        if (! $this->isStaff() || $item === null || ($item['superadmin_only'] ?? false)) {
            return false;
        }

        $granted = $this->platformPermissions()[$key] ?? null;

        return $granted === Menu::ABILITY_MANAGE || ($granted === Menu::ABILITY_VIEW && $ability === Menu::ABILITY_VIEW);
    }

    /**
     * Izin dari semua role platform milik user, dimuat sekali per request.
     * Kalau satu menu ada di beberapa role, yang tertinggi (manage) yang dipakai.
     */
    private function platformPermissions(): array
    {
        return $this->platformPermissionCache ??= RolePermission::query()
            ->join('role_user', 'role_user.role_id', '=', 'role_permissions.role_id')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->where('role_user.user_id', $this->getKey())
            ->where('roles.scope', Role::SCOPE_PLATFORM)
            ->get(['role_permissions.permission_key', 'role_permissions.ability'])
            ->reduce(function (array $carry, RolePermission $permission) {
                if (($carry[$permission->permission_key] ?? null) !== Menu::ABILITY_MANAGE) {
                    $carry[$permission->permission_key] = $permission->ability;
                }

                return $carry;
            }, []);
    }

    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim((string) $keyword);
        $digits = preg_replace('/^(62|0)/', '', preg_replace('/\D/', '', $keyword));

        return $query->when($keyword !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
            ->where('name', 'like', "%{$keyword}%")
            ->orWhere('email', 'like', "%{$keyword}%")
            ->when($digits !== '', fn (Builder $p) => $p->orWhere('handphone', 'like', "%{$digits}%"))));
    }

    /**
     * Keluarkan dari semua perangkat: cookie "remember me" tidak berlaku lagi
     * dan sesi yang tersimpan (driver database) dihapus.
     */
    public function logoutEverywhere(?string $exceptSessionId = null): void
    {
        $this->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $this->getKey())
                ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
                ->delete();
        }
    }

    /**
     * Nomor HP disimpan seragam berawalan 62 (siap dipakai WhatsApp):
     * "0812-3456-789", "+62 812..." dan "812..." jadi "62812...".
     */
    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        $digits = preg_replace('/^(62|0)/', '', $digits);

        return $digits === '' ? '' : '62'.$digits;
    }
}
