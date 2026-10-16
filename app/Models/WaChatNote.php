<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaChatNote extends Model
{
    use HasUuids;

    protected $fillable = ['note'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
