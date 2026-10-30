<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Pengaturan Invoice tingkat platform: biaya layanan bizbos & batas nominal. */
class InvoiceSetting extends Model
{
    use HasUuids;

    protected $fillable = ['fee_percent', 'fee_flat', 'min_amount', 'max_amount', 'api_expiry_minutes'];

    protected function casts(): array
    {
        return ['fee_percent' => 'float', 'fee_flat' => 'integer', 'min_amount' => 'integer', 'max_amount' => 'integer', 'api_expiry_minutes' => 'integer'];
    }

    /** Baris pengaturan satu-satunya. Baris yang baru dibuat dibaca ulang supaya nilai default database (batas nominal dll.) ikut terisi. */
    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([])->refresh();
    }

    /** Biaya layanan dari total yang dibayar customer; dibulatkan ke atas, tidak pernah melebihi total. */
    public function feeFor(int $total): int
    {
        return min($total, (int) ceil($total * $this->fee_percent / 100 + $this->fee_flat));
    }

    /** Pesan untuk total di luar batas pembayaran online, menyebut batasnya supaya jelas. */
    public function limitMessage(int $total): string
    {
        $rp = fn (int $v) => 'Rp '.number_format($v, 0, ',', '.');

        return $total < $this->min_amount
            ? 'Minimal pembayaran online '.$rp($this->min_amount).', total tagihan ini '.$rp($total).'.'
            : 'Maksimal pembayaran online '.$rp($this->max_amount).', total tagihan ini '.$rp($total).'.';
    }
}
