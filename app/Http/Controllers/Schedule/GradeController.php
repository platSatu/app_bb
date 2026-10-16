<?php

namespace App\Http\Controllers\Schedule;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class GradeController extends ScheduleResourceController
{
    protected ?string $usageColumn = 'grade_id';

    protected string $resource = 'grades';

    protected array $columns = ['Bidang › Kategori', 'Harga Paket', 'Per Pertemuan', 'Pengajar'];

    protected array $with = ['category:id,name,subject_id', 'category.subject:id,name', 'teachers:id,name'];

    protected function extraRules(Request $request, string $branchId, Model $item): array
    {
        return [
            'price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'meetings_per_month' => ['required', 'integer', 'min:1', 'max:31'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:600'],
            'teacher_percent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ];
    }

    protected function prepare(Request $request): void
    {
        $request->merge(['price' => preg_replace('/\D/', '', (string) $request->input('price'))]);
    }

    protected function attributes(): array
    {
        return [
            'price' => 'harga paket',
            'meetings_per_month' => 'jumlah pertemuan',
            'duration_minutes' => 'durasi',
            'teacher_percent' => 'bagian pengajar',
        ];
    }
}
