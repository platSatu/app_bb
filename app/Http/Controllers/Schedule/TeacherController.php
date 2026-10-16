<?php

namespace App\Http\Controllers\Schedule;

use App\Models\ScheduleGrade;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherController extends ScheduleResourceController
{
    protected ?string $usageColumn = 'teacher_id';

    protected string $resource = 'teachers';

    protected array $columns = ['Kontak', 'Grade yang Diajar'];

    protected array $with = ['grades:id,name,category_id', 'grades.category:id,name,subject_id', 'grades.category.subject:id,name'];

    protected array $searchable = ['name', 'email', 'phone'];

    protected bool $uniqueName = false;

    protected function prepare(Request $request): void
    {
        $request->merge([
            'phone' => User::normalizePhone($request->input('phone')) ?: null,
            'email' => filled($request->input('email')) ? strtolower(trim($request->input('email'))) : null,
        ]);
    }

    protected function extraRules(Request $request, string $branchId, Model $item): array
    {
        $unique = fn (string $column) => Rule::unique($item->getTable(), $column)->where('branch_id', $branchId)->ignore($item->getKey());

        return [
            'email' => ['nullable', 'email', 'max:255', $unique('email')],
            'phone' => ['nullable', 'string', 'min:10', 'max:20', $unique('phone')],
            'grade_ids' => ['nullable', 'array'],
            // Grade harus dari branch yang sama dengan pengajar.
            'grade_ids.*' => ['string', Rule::exists('schedule_grades', 'id')->where('branch_id', $branchId)],
        ];
    }

    protected function attributes(): array
    {
        return ['phone' => 'nomor WhatsApp', 'grade_ids.*' => 'grade'];
    }

    protected function saved(Request $request, Model $item, array $data): void
    {
        $item->grades()->sync($data['grade_ids'] ?? []);
    }

    protected function formData(Request $request, Model $item): array
    {
        return [
            'grades' => ScheduleGrade::accessibleBy($request->user())
                ->with(['branch:id,name', ...ScheduleGrade::LABEL_WITH])
                ->when($item->exists, fn ($q) => $q->where('branch_id', $item->branch_id))
                // Superadmin membuat pengajar baru: grade dipilih setelah branch tersimpan (lewat Edit).
                ->when(! $item->exists && $request->user()->isPlatformTeam(), fn ($q) => $q->whereRaw('1 = 0'))
                ->get()
                ->sortBy(fn ($grade) => $grade->label())
                ->groupBy('branch_id'),
            'selectedGrades' => collect(old('grade_ids', $item->exists ? $item->grades()->pluck('schedule_grades.id')->all() : [])),
        ];
    }
}
