<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduleBranch;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleCategory extends Model
{
    use BelongsToScheduleBranch, HasActiveStatus, HasUuids;

    public const LABEL_WITH = ['subject:id,name'];

    protected $fillable = ['name', 'description', 'status'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'subject_id');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(ScheduleGrade::class, 'category_id');
    }

    public function label(): string
    {
        return $this->subject?->name.' › '.$this->name;
    }
}
