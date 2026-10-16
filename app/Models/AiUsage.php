<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    use HasUuids;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'count' => 'integer'];
    }
}
