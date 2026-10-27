<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat perubahan kategori tiket: kolom apa berubah dari apa menjadi apa, oleh siapa. Hanya ditambah. */
class TicketCategoryChange extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const FIELDS = [
        'name' => 'Nama', 'price' => 'Harga', 'quota' => 'Jumlah dijual', 'max_per_order' => 'Maks. per orang',
        'description' => 'Keterangan', 'sale_starts_at' => 'Mulai dijual', 'sale_ends_at' => 'Berhenti dijual', 'status' => 'Status',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    /** Catat hanya kolom yang benar-benar berubah. $before/$after = nilai siap tampil (lihat displayValues). */
    public static function record(TicketCategory $category, array $before, ?string $actorId): void
    {
        $after = self::displayValues($category);
        $changes = collect($after)->filter(fn ($value, $key) => ($before[$key] ?? null) !== $value)
            ->map(fn ($value, $key) => [$before[$key] ?? null, $value])->all();

        if (! $changes) {
            return;
        }

        (new self())->forceFill([
            'page_id' => $category->page_id, 'category_id' => $category->getKey(), 'actor_id' => $actorId,
            'category_name' => $category->name, 'changes' => $changes,
        ])->save();
    }

    public static function displayValues(TicketCategory $category): array
    {
        $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
        $date = fn ($d) => $d ? $d->format('d/m/Y H:i') : '-';

        return [
            'name' => (string) $category->name,
            'price' => $rp($category->price),
            'quota' => (string) $category->quota,
            'max_per_order' => (string) $category->max_per_order,
            'description' => (string) ($category->description ?: '-'),
            'sale_starts_at' => $date($category->sale_starts_at),
            'sale_ends_at' => $date($category->sale_ends_at),
            'status' => TicketCategory::STATUS_LABELS[$category->status] ?? (string) $category->status,
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
