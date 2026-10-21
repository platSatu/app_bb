<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentCallback extends Model
{
    use HasUuids;

    protected $fillable = ['provider', 'merchant_order_id', 'payload', 'signature_valid', 'result', 'message', 'ip_address'];

    public const RESULT_LABELS = [
        'paid' => 'Lunas, tiket diterbitkan',
        'refund_needed' => 'Dibayar terlambat, kuota habis (perlu refund)',
        'already_processed' => 'Sudah diproses sebelumnya',
        'failed' => 'Pembayaran gagal/dibatalkan',
        'amount_mismatch' => 'Ditolak: nominal tidak cocok',
        'not_confirmed' => 'Ditolak: belum lunas saat dicek ke Duitku',
        'not_found' => 'Nomor pesanan tidak ditemukan',
        'invalid_signature' => 'Ditolak: tanda tangan tidak valid',
    ];

    public function resultLabel(): string
    {
        return self::RESULT_LABELS[$this->result] ?? ($this->result ?: 'Sedang diproses');
    }

    public function resultBadge(): string
    {
        return match ($this->result) {
            'paid' => 'bg-success-subtle text-success',
            'already_processed' => 'bg-secondary-subtle text-secondary',
            'failed', 'refund_needed' => 'bg-warning-subtle text-warning',
            default => 'bg-danger-subtle text-danger',
        };
    }

    public function amount(): int
    {
        return (int) round((float) ($this->payload['amount'] ?? 0));
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'signature_valid' => 'boolean'];
    }
}
