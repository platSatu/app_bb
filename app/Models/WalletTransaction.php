<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WalletTransaction extends Model
{
    use HasUuids;

    public const CREDIT = 'credit';
    public const DEBIT = 'debit';

    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_REFERRAL_BONUS = 'referral_bonus';
    public const TYPE_TICKET_SALE = 'ticket_sale';
    public const TYPE_INVOICE_PAYMENT = 'invoice_payment';
    public const TYPE_WITHDRAWAL = 'withdrawal';
    public const TYPE_WITHDRAWAL_FEE = 'withdrawal_fee';
    public const TYPE_WITHDRAWAL_REFUND = 'withdrawal_refund';
    public const TYPE_DISBURSEMENT = 'disbursement';
    public const TYPE_DISBURSEMENT_FEE = 'disbursement_fee';
    public const TYPE_DISBURSEMENT_REFUND = 'disbursement_refund';

    public const TYPE_LABELS = [
        self::TYPE_DEPOSIT => 'Top Up',
        self::TYPE_PURCHASE => 'Pembelian',
        self::TYPE_REFUND => 'Refund',
        self::TYPE_ADJUSTMENT => 'Koreksi Admin',
        self::TYPE_REFERRAL_BONUS => 'Bonus Referral',
        self::TYPE_TICKET_SALE => 'Penjualan Tiket',
        self::TYPE_INVOICE_PAYMENT => 'Pembayaran Invoice',
        self::TYPE_WITHDRAWAL => 'Tarik Saldo',
        self::TYPE_WITHDRAWAL_FEE => 'Biaya Tarik Saldo',
        self::TYPE_WITHDRAWAL_REFUND => 'Pengembalian Tarik Saldo',
        self::TYPE_DISBURSEMENT => 'Disbursement',
        self::TYPE_DISBURSEMENT_FEE => 'Biaya Disbursement',
        self::TYPE_DISBURSEMENT_REFUND => 'Pengembalian Disbursement',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_before' => 'integer', 'balance_after' => 'integer'];
    }

    protected static function booted(): void
    {
        // Ledger tidak boleh diubah/dihapus; koreksi selalu lewat transaksi baru.
        static::updating(fn () => throw new LogicException('Riwayat saldo tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Riwayat saldo tidak boleh dihapus.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCredit(): bool
    {
        return $this->direction === self::CREDIT;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst($this->type);
    }
}
