<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat stok kategori tiket. Ditulis di dalam transaksi yang sama dengan perubahan stoknya. */
class TicketStockLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const ACTION_RESERVE = 'reserve';
    public const ACTION_RELEASE = 'release';
    public const ACTION_SELL = 'sell';
    public const ACTION_QUOTA = 'quota';

    public const ACTION_LABELS = [
        self::ACTION_RESERVE => 'Ditahan (menunggu bayar)',
        self::ACTION_RELEASE => 'Dilepas (batal/kedaluwarsa)',
        self::ACTION_SELL => 'Terjual',
        self::ACTION_QUOTA => 'Jumlah tiket diubah admin',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** $before = snapshot() sebelum diubah; tidak mencatat apa pun kalau ternyata tidak ada perubahan. */
    public static function record(TicketCategory $category, array $before, string $action, ?string $orderId = null, ?string $actorId = null, ?string $note = null): void
    {
        $after = self::snapshot($category);

        if ($after === $before) {
            return;
        }

        (new self())->forceFill([
            'page_id' => $category->page_id,
            'category_id' => $category->getKey(),
            'order_id' => $orderId,
            'actor_id' => $actorId,
            'action' => $action,
            'quota_before' => $before['quota'], 'quota_after' => $after['quota'],
            'reserved_before' => $before['reserved'], 'reserved_after' => $after['reserved'],
            'sold_before' => $before['sold'], 'sold_after' => $after['sold'],
            'note' => $note,
        ])->save();
    }

    public static function snapshot(TicketCategory $category): array
    {
        return ['quota' => (int) $category->quota, 'reserved' => (int) $category->reserved, 'sold' => (int) $category->sold];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'order_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }

    /** Ringkasan perubahan, mis. "Ditahan +2 · Terjual +2". */
    public function changes(): string
    {
        return collect(['quota' => 'Jumlah dijual', 'reserved' => 'Ditahan', 'sold' => 'Terjual'])
            ->map(fn ($label, $key) => [$label, $this->{$key.'_after'} - $this->{$key.'_before'}])
            ->filter(fn ($row) => $row[1] !== 0)
            ->map(fn ($row) => $row[0].' '.($row[1] > 0 ? '+' : '').$row[1])
            ->implode(' · ');
    }
}
