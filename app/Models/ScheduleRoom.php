<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduleBranch;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleRoom extends Model
{
    use BelongsToScheduleBranch, HasActiveStatus, HasUuids;

    public const LABEL_WITH = [];

    protected $fillable = ['name', 'description', 'status'];

    public function subjects(): HasMany
    {
        return $this->hasMany(ScheduleSubject::class, 'room_id');
    }
}
