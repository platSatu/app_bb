<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduleBranch;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** Pengajar. Bisa terhubung ke akun portal (user_id), satu akun bisa mengajar di beberapa branch. */
class ScheduleTeacher extends Authenticatable
{
    use BelongsToScheduleBranch, HasActiveStatus, HasUuids;

    public const LABEL_WITH = [];

    protected $fillable = ['name', 'email', 'phone', 'description', 'status'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'last_login_at' => 'datetime'];
    }

    public function grades(): BelongsToMany
    {
        return $this->belongsToMany(ScheduleGrade::class, 'schedule_teacher_grades', 'teacher_id', 'grade_id');
    }

    /** Akun portal (orang tua/murid atau pengajar) yang terhubung. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
