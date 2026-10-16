<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class QuizOption extends Model
{
    use HasUuids;

    protected $fillable = ['label', 'is_correct'];

    // Penanda jawaban benar tidak boleh ikut ter-serialize ke publik.
    protected $hidden = ['is_correct'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }
}
