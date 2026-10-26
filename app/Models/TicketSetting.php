<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Biaya layanan bizbos untuk setiap tiket berbayar yang terjual (diatur superadmin). */
class TicketSetting extends Model
{
    use HasUuids;

    public const PAYER_ORGANIZER = 'organizer';
    public const PAYER_BUYER = 'buyer';

    public const PAYER_LABELS = [
        self::PAYER_ORGANIZER => 'Ditanggung penyelenggara',
        self::PAYER_BUYER => 'Ditanggung pembeli',
    ];

    public const MAX_PERCENT = 20;

    protected $fillable = ['fee_percent'];

    protected function casts(): array
    {
        return ['fee_percent' => 'decimal:2'];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([])->refresh();
    }

    /**
     * Biaya layanan dari harga tiket, dibulatkan ke atas sampai rupiah. Dihitung dengan bilangan bulat
     * (basis poin) supaya hasilnya sama persis di server dan di simulasi browser.
     */
    public static function feeFor(int $subtotal, string|float $percent): int
    {
        $bps = (int) round((float) $percent * 100);

        return $subtotal > 0 && $bps > 0 ? intdiv($subtotal * $bps + 9999, 10000) : 0;
    }
}
