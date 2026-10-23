<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu pendaftar dari form: jawaban, pesan WhatsApp, tagihan (opsional), dan kehadiran (opsional). */
class WaFormSubmission extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'datetime', 'qr_sent_at' => 'datetime', 'attended_at' => 'datetime'];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(WaFormIntegration::class, 'integration_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Kode QR: 10 karakter tanpa huruf/angka yang mirip (0/O, 1/I), mudah diketik petugas. */
    public static function newCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = implode('', array_map(fn () => $chars[random_int(0, strlen($chars) - 1)], range(1, 10)));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function ticketUrl(): ?string
    {
        return $this->code ? route('form-ticket.show', $this->code) : null;
    }

    /** Lunas, atau memang tidak perlu bayar. */
    public function isPaid(): bool
    {
        return ! $this->invoice_id || $this->invoice?->status === Invoice::STATUS_PAID;
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->phone ? '+'.$this->phone : 'Tanpa nama');
    }

    public function paymentLabel(): string
    {
        return match (true) {
            ! $this->invoice_id => '-',
            $this->invoice?->status === Invoice::STATUS_PAID => 'Lunas',
            default => 'Belum bayar',
        };
    }
}
