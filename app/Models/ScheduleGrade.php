<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduleBranch;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Harga paket per bulan (mis. Rp 500.000 untuk 4 pertemuan). Pertemuan ke-5 dalam sebulan tidak dibuat.
 * Honor dihitung per murid per pertemuan: harga / pertemuan x persen pengajar, sisanya owner.
 */
class ScheduleGrade extends Model
{
    use BelongsToScheduleBranch, HasActiveStatus, HasUuids;

    public const LABEL_WITH = ['category:id,name,subject_id', 'category.subject:id,name'];

    protected $fillable = ['name', 'description', 'price', 'meetings_per_month', 'duration_minutes', 'teacher_percent', 'status'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'meetings_per_month' => 'integer', 'duration_minutes' => 'integer', 'teacher_percent' => 'float'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ScheduleCategory::class, 'category_id');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(ScheduleTeacher::class, 'schedule_teacher_grades', 'grade_id', 'teacher_id');
    }

    public function label(): string
    {
        return $this->category?->subject?->name.' › '.$this->category?->name.' › '.$this->name;
    }

    public function pricePerMeeting(): int
    {
        return $this->meetings_per_month > 0 ? (int) round($this->price / $this->meetings_per_month) : 0;
    }

    public function teacherShare(): int
    {
        return (int) round($this->pricePerMeeting() * $this->teacher_percent / 100);
    }

    /** Sisa setelah bagian pengajar, supaya tidak ada rupiah yang hilang karena pembulatan. */
    public function ownerShare(): int
    {
        return $this->pricePerMeeting() - $this->teacherShare();
    }

    public function ownerPercent(): float
    {
        return round(100 - $this->teacher_percent, 2);
    }
}
