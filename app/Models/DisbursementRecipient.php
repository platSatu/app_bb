<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Rekening penerima disbursement milik satu branch. Nama pemilik diambil dari bank (inquiry Duitku). */
class DisbursementRecipient extends Model
{
    use HasUuids, ScopedToBranch;

    public const ADMIN_PERMISSION = 'superadmin.invoices';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [];

    protected $hidden = ['account_number', 'account_hash'];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted'];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function masked(): string
    {
        return $this->bank_name.' ••••'.$this->account_last4;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'label' => $this->label,
            'bank_code' => $this->bank_code,
            'bank_name' => $this->bank_name,
            'account_last4' => $this->account_last4,
            'account_name' => $this->account_name,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
