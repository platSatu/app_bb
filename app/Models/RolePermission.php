<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermission extends Model
{
    use HasUuids;

    protected $fillable = ['permission_key', 'ability'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
