<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REPLACED = 'replaced';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu Verifikasi',
        self::STATUS_APPROVED => 'Aktif',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_REPLACED => 'Diganti',
    ];

    protected $fillable = [];

    protected $hidden = ['account_number', 'account_hash'];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hash(string $bankCode, string $number): string
    {
        return hash('sha256', $bankCode.'|'.$number);
    }

    public function masked(): string
    {
        return $this->bank_name.' ••••'.$this->account_last4;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
