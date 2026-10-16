<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduleBranch;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** Murid: tersambung ke Buku Kontak branch. Login ke dashboard murid sendiri (tahap berikutnya). */
class ScheduleStudent extends Authenticatable
{
    use BelongsToScheduleBranch, HasActiveStatus, HasUuids;

    public const LABEL_WITH = [];

    protected $fillable = ['name', 'email', 'phone', 'guardian_name', 'description', 'status'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'last_login_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
