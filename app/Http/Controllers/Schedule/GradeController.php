<?php

namespace App\Http\Controllers\Schedule;

use App\Models\ScheduleGrade;
use App\Models\ScheduleTeacher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GradeController extends ScheduleResourceController
{
    protected ?string $usageColumn = 'grade_id';

    protected string $resource = 'grades';

    protected array $columns = ['Bidang › Kategori', 'Harga', 'Per Pertemuan', 'Pengajar'];

    protected array $with = ['category:id,name,subject_id', 'category.subject:id,name', 'teachers:id,name'];

    protected function extraRules(Request $request, string $branchId, Model $item): array
    {
        $package = $request->input('billing') === ScheduleGrade::BILLING_PACKAGE;

        return [
            'billing' => ['required', Rule::in(array_keys(ScheduleGrade::BILLING_LABELS))],
            'price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            // Bulanan: pertemuan per bulan (maks. 31). Paket: jumlah pertemuan dalam satu paket.
            'meetings_per_month' => ['required', 'integer', 'min:1', 'max:'.($package ? 200 : 31)],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:600'],
            'teacher_percent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'teacher_ids' => ['nullable', 'array'],
            // Pengajar harus dari branch yang sama dengan grade-nya.
            'teacher_ids.*' => ['string', Rule::exists('schedule_teachers', 'id')->where('branch_id', $branchId)],
        ];
    }

    protected function saved(Request $request, Model $item, array $data): void
    {
        $item->teachers()->sync($data['teacher_ids'] ?? []);
    }

    /** Pengajar dipilih di sini (bukan lagi di form Pengajar), memakai tabel penghubung yang sama. */
    protected function formData(Request $request, Model $item): array
    {
        return [
            'teachers' => ScheduleTeacher::accessibleBy($request->user())
                ->with('branch:id,name')
                ->when($item->exists, fn ($q) => $q->where('branch_id', $item->branch_id))
                // Superadmin membuat grade baru: pengajar dipilih setelah branch tersimpan (lewat Edit).
                ->when(! $item->exists && $request->user()->isPlatformTeam(), fn ($q) => $q->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get()
                ->groupBy('branch_id'),
            'selectedTeachers' => collect(old('teacher_ids', $item->exists ? $item->teachers()->pluck('schedule_teachers.id')->all() : [])),
        ];
    }

    protected function prepare(Request $request): void
    {
        $request->merge(['price' => preg_replace('/\D/', '', (string) $request->input('price'))]);
    }

    protected function attributes(): array
    {
        return [
            'billing' => 'jenis harga',
            'price' => 'harga',
            'meetings_per_month' => 'jumlah pertemuan',
            'duration_minutes' => 'durasi',
            'teacher_percent' => 'bagian pengajar',
            'teacher_ids.*' => 'pengajar',
        ];
    }
}
