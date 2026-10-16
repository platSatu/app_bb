<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model
{
    use HasUuids;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'points' => 'integer'];
    }
}
