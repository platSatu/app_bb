<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduleBranch;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\URL;

/** Murid: tersambung ke customer Schedules di branch. Bisa terhubung ke akun portal orang tua/murid (user_id). */
class ScheduleStudent extends Authenticatable
{
    use BelongsToScheduleBranch, HasActiveStatus, HasUuids;

    public const LABEL_WITH = [];

    protected $fillable = ['name', 'email', 'phone', 'guardian_name', 'description', 'status', 'left_on'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'last_login_at' => 'datetime', 'left_on' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Akun portal (orang tua/murid atau pengajar) yang terhubung. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Isi QR kartu absen: id murid + tanda tangan dari app key, jadi QR murid lain tidak bisa dibuat sendiri. */
    public function qrCode(): string
    {
        return 'BBS.'.$this->getKey().'.'.self::qrSignature($this->getKey());
    }

    /** Id murid dari isi QR; null kalau formatnya salah atau tanda tangannya tidak cocok (QR palsu). */
    public static function idFromQr(string $code): ?string
    {
        if (! preg_match('/^BBS\.([0-9a-f-]{36})\.([0-9a-f]{16})$/', trim($code), $m)) {
            return null;
        }

        return hash_equals(self::qrSignature($m[1]), $m[2]) ? $m[1] : null;
    }

    private static function qrSignature(string $id): string
    {
        return substr(hash_hmac('sha256', 'schedule-card|'.$id, (string) config('app.key')), 0, 16);
    }

    /** Link kartu absen yang bisa dibuka tanpa login (dikirim lewat email / WhatsApp). */
    public function cardUrl(): string
    {
        return URL::signedRoute('portal.card', ['student' => $this->getKey()]);
    }

    /** Pesan WhatsApp berisi link kartu absen (dikirim otomatis atau lewat wa.me). Relasi branch sebaiknya sudah dimuat. */
    public function cardMessage(): string
    {
        return 'Halo'.($this->guardian_name ? ' '.$this->guardian_name : '').", ini kartu absen {$this->name} di {$this->branch?->name}.\n"
            ."Tunjukkan QR-nya ke pengajar saat les dimulai, kehadiran langsung tercatat:\n".$this->cardUrl();
    }
}
