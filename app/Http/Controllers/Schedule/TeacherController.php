<?php

namespace App\Http\Controllers\Schedule;

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
        ];
    }

    protected function attributes(): array
    {
        return ['phone' => 'nomor WhatsApp'];
    }

    // Grade yang diajar TIDAK lagi dipilih di sini, tapi di form Grade (tombol Pengajar di tabel Grade).
    // Penyimpannya ikut dicabut bersama pemilihnya: kalau hanya pemilihnya yang dihapus, setiap simpan
    // pengajar akan mengirim grade_ids kosong dan menghapus seluruh grade yang sudah ditugaskan.
    // Kolom "Grade yang Diajar" di tabel tetap ditampilkan, hanya sudah tidak bisa diubah dari sini.
}
