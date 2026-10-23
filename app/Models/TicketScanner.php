<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Link ber-token + PIN untuk perangkat di lokasi event. Jenisnya menentukan halaman yang terbuka. */
class TicketScanner extends Model
{
    use HasActiveStatus, HasUuids;

    // Registrasi ulang di pintu masuk (scan e-ticket / ID card).
    public const TYPE_CHECKIN = 'checkin';
    // Kiosk cetak mandiri: peserta scan e-ticket sendiri, ID card langsung tercetak.
    public const TYPE_KIOSK = 'kiosk';
    // Absensi per sesi/kelas: hanya menerima QR ID card.
    public const TYPE_ATTENDANCE = 'attendance';

    public const TYPES = [self::TYPE_CHECKIN, self::TYPE_KIOSK, self::TYPE_ATTENDANCE];

    protected $fillable = ['name', 'status'];

    protected $hidden = ['token', 'pin_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'page_id');
    }

    public function url(): string
    {
        return route('scan.show', $this->token);
    }

    public function isType(string $type): bool
    {
        return ($this->type ?: self::TYPE_CHECKIN) === $type;
    }
}
