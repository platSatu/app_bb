<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    use HasUuids;

    protected $fillable = ['daily_quota'];

    protected function casts(): array
    {
        return ['daily_quota' => 'integer'];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['daily_quota' => 20]);
    }
}
