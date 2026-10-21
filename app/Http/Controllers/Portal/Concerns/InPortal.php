<?php

namespace App\Http\Controllers\Portal\Concerns;

use App\Models\ScheduleSession;
use App\Models\ScheduleStudent;
use App\Models\ScheduleTeacher;
use App\Models\User;
use App\Services\Subscription\AppAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Data portal selalu dimulai dari murid/pengajar milik akun yang sedang masuk, di branch yang paket Jadwal-nya aktif. */
trait InPortal
{
    protected const SESSION_WITH = ['branch:id,name', 'teacher:id,name', 'room:id,name', 'grade:id,name,category_id', 'grade.category:id,name,subject_id', 'grade.category.subject:id,name'];

    private const COLORS = ['primary', 'success', 'warning', 'info', 'danger', 'secondary'];

    protected function students(User $user): Collection
    {
        return ScheduleStudent::where('user_id', $user->getKey())->whereIn('branch_id', AppAccess::activeBranches('schedules'))->with('branch:id,name')->orderBy('name')->get(['id', 'name', 'branch_id', 'status']);
    }

    protected function teachers(User $user): Collection
    {
        return ScheduleTeacher::where('user_id', $user->getKey())->whereIn('branch_id', AppAccess::activeBranches('schedules'))->with('branch:id,name')->get(['id', 'name', 'branch_id', 'status']);
    }

    /** Sesi yang diikuti murid-murid ini; absensi yang dimuat hanya milik mereka. */
    protected function studentSessions(Collection $studentIds): Builder
    {
        return ScheduleSession::with([...self::SESSION_WITH, 'attendances' => fn ($q) => $q->whereIn('student_id', $studentIds)->with('student:id,name')])
            ->whereHas('attendances', fn ($q) => $q->whereIn('student_id', $studentIds));
    }

    /** Pill tab: [id => label]. Nama anak yang sama di dua branch diberi nama branch. */
    protected function tabs(Collection $items, callable $label): array
    {
        return $items->mapWithKeys(fn ($item) => [$item->getKey() => $label($item)])->all();
    }

    /** Pilihan tab dari query string; hanya id yang memang milik akun ini. */
    protected function selected(Request $request, string $key, array $tabs): ?string
    {
        $value = (string) $request->query($key);

        return array_key_exists($value, $tabs) ? $value : null;
    }

    /** Warna tetap per branch supaya mudah dibedakan di tabel. */
    protected function branchColors(Collection $branchIds): array
    {
        return $branchIds->unique()->sort()->values()->mapWithKeys(fn ($id, $i) => [$id => self::COLORS[$i % count(self::COLORS)]])->all();
    }
}
