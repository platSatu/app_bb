<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Nomor HP & email yang sudah pernah memakai trial. */
class TrialClaim extends Model
{
    use HasUuids;

    protected $fillable = [];
}
