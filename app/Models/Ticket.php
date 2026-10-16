<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasUuids;

    protected $fillable = [];

    // Token = isi QR & kunci link; tidak boleh ikut ter-serialize.
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'checked_in_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'order_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'page_id');
    }

    public function url(string $pageSlug): string
    {
        return route('eticket.show', [$pageSlug, $this->number, $this->token]);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(TicketScanner::class, 'checked_in_scanner_id');
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }
}
