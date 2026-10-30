<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasUuids, ScopedToBranch;

    public const ADMIN_PERMISSION = 'superadmin.invoices';

    public const SOURCE_DASHBOARD = 'dashboard';
    public const SOURCE_API = 'api';
    // Pendaftaran Google Form berbayar (WhatsApp > Third Party > Google Form).
    public const SOURCE_FORM = 'form';

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID = 'paid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_UNPAID => 'Belum Bayar',
        self::STATUS_PAID => 'Lunas',
        self::STATUS_EXPIRED => 'Kedaluwarsa',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    private const BADGES = [
        self::STATUS_UNPAID => 'bg-warning-subtle text-warning',
        self::STATUS_PAID => 'bg-success-subtle text-success',
        self::STATUS_EXPIRED => 'bg-secondary-subtle text-secondary',
        self::STATUS_CANCELLED => 'bg-danger-subtle text-danger',
    ];

    protected $fillable = [];

    protected $hidden = ['token'];

    protected static function booted(): void
    {
        // Pendaftaran Google Form lunas: kirim QR registrasi ulang lewat WhatsApp (setelah transaksi selesai).
        static::updated(function (self $invoice) {
            if ($invoice->source === self::SOURCE_FORM && $invoice->wasChanged('status') && $invoice->status === self::STATUS_PAID) {
                $id = $invoice->getKey();
                dispatch(fn () => app(\App\Services\WhatsApp\FormRelay::class)->paid($id))->afterCommit();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'late_fee' => 'integer',
            'paid_amount' => 'integer',
            'fee' => 'integer',
            'net_amount' => 'integer',
            'apply_late_fee' => 'boolean',
            'due_date' => 'date',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InvoiceCategory::class, 'category_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InvoiceBatch::class, 'batch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(UserCompany::class, 'company_id');
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(InvoiceApiClient::class, 'api_client_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function isUnpaid(): bool
    {
        return $this->status === self::STATUS_UNPAID;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /** Denda berjalan hari ini (belum lunas) atau denda yang dibayar (sudah lunas). */
    public function currentLateFee(): int
    {
        if (! $this->isUnpaid()) {
            return $this->late_fee;
        }

        if (! $this->apply_late_fee || ! $this->due_date || ! $this->category) {
            return 0;
        }

        return $this->category->lateFeeFor($this->amount, (int) $this->due_date->diffInDays(today(), false));
    }

    public function totalDue(): int
    {
        return $this->amount + $this->currentLateFee();
    }

    public function isOverdue(): bool
    {
        return $this->isUnpaid() && $this->due_date && $this->due_date->lt(today());
    }

    /** Denda keterlambatan berlaku untuk invoice ini (dicentang di invoice & kategorinya punya aturan denda). */
    public function chargesLateFee(): bool
    {
        return $this->apply_late_fee && $this->category?->hasLateFee();
    }

    /**
     * Lewat jatuh tempo tanpa denda = link bayar ditutup (kedaluwarsa). Dengan denda: tetap bisa dibayar, dendanya berjalan.
     * Hanya untuk invoice tanpa expires_at sendiri (dashboard, Google Form); invoice API mengikuti expires_at-nya.
     */
    public function isPastDueClosed(): bool
    {
        return $this->isOverdue() && $this->expires_at === null && ! $this->chargesLateFee();
    }

    /** Query pasangan isPastDueClosed(), dipakai scheduler & daftar tagihan pembayar. */
    public function scopePastDueClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNPAID)
            ->whereNull('expires_at')
            ->where('due_date', '<', today()->toDateString())
            ->where(fn (Builder $q) => $q->where('apply_late_fee', false)
                ->orWhereNull('category_id')
                ->orWhereIn('category_id', InvoiceCategory::query()->where('late_fee_mode', 'none')->select('id')));
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_API => 'API',
            self::SOURCE_FORM => 'Google Form',
            default => 'Dashboard',
        };
    }

    public function payUrl(): string
    {
        return route('invoice.pay', $this->token);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::BADGES[$this->status] ?? 'bg-light text-dark';
    }

    /** Bentuk data untuk API & webhook. Token tidak pernah dikirim selain di checkout_url. */
    /** Link halaman bayar Duitku yang masih berlaku (null kalau belum ada / sudah lewat / invoice tidak unpaid). */
    public function openPaymentUrl(): ?string
    {
        if (! $this->isUnpaid()) {
            return null;
        }

        return $this->payments()->where('status', InvoicePayment::STATUS_PENDING)->where('expires_at', '>', now())
            ->whereNotNull('payment_url')->latest()->value('payment_url');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'number' => $this->number,
            'status' => $this->status,
            'description' => $this->description,
            'customer' => ['name' => $this->customer_name, 'email' => $this->customer_email, 'phone' => $this->customer_phone],
            'amount' => $this->amount,
            'late_fee' => $this->currentLateFee(),
            'total' => $this->isPaid() ? $this->paid_amount : $this->totalDue(),
            'fee' => $this->fee,
            'net_amount' => $this->net_amount,
            'payment_method' => $this->payment_method,
            'checkout_url' => $this->payUrl(),
            'payment_url' => $this->openPaymentUrl(),
            'due_date' => $this->due_date?->toDateString(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
