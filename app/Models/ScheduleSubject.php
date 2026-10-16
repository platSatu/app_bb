<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduleBranch;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bidang / mata pelajaran, berada di satu ruangan. */
class ScheduleSubject extends Model
{
    use BelongsToScheduleBranch, HasActiveStatus, HasUuids;

    public const LABEL_WITH = ['room:id,name'];

    protected $fillable = ['name', 'description', 'status'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(ScheduleRoom::class, 'room_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ScheduleCategory::class, 'subject_id');
    }

    public function label(): string
    {
        return $this->name.' ('.$this->room?->name.')';
    }
}
