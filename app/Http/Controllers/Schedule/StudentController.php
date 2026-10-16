<?php

namespace App\Http\Controllers\Schedule;

use App\Models\ScheduleAttendance;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\Customer\CustomerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentController extends ScheduleResourceController
{
    protected string $resource = 'students';

    protected array $columns = ['Kontak', 'Orang Tua / Wali'];

    protected array $searchable = ['name', 'email', 'phone', 'guardian_name'];

    protected bool $uniqueName = false;

    public function __construct(private readonly CustomerService $customers)
    {
    }

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
            'guardian_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    protected function attributes(): array
    {
        return ['phone' => 'nomor WhatsApp', 'guardian_name' => 'nama orang tua / wali'];
    }

    protected function isUsed(Model $item): bool
    {
        return DB::table('schedule_participants')->where('student_id', $item->getKey())->exists()
            || ScheduleAttendance::where('student_id', $item->getKey())->exists();
    }

    /** Murid yang punya WA/email ikut tercatat di Buku Kontak branch. */
    protected function saved(Request $request, Model $item, array $data): void
    {
        if (! $item->phone && ! $item->email) {
            return;
        }

        $branch = UserBranch::whereKey($item->branch_id)->lockForUpdate()->firstOrFail();
        $customer = $this->customers->resolve($branch, $item->name, $item->phone, $item->email, 'schedule');
        $item->forceFill(['customer_id' => $customer->getKey()])->save();
    }
}
