<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketScanner extends Model
{
    use HasActiveStatus, HasUuids;

    protected $fillable = ['name', 'status'];

    protected $hidden = ['token', 'pin_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'page_id');
    }

    public function url(): string
    {
        return route('scan.show', $this->token);
    }
}
