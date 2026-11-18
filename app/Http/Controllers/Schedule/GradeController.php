<?php

namespace App\Http\Controllers\Schedule;

use App\Models\ScheduleGrade;
use App\Models\ScheduleRoom;
use App\Models\ScheduleRoutine;
use App\Models\ScheduleTeacher;
use App\Services\Schedule\ScheduleRoutineService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GradeController extends ScheduleResourceController
{
    /** Batas baris jam buka sekali simpan, dan batas slot per baris. */
    private const MAX_ROWS = 12;

    private const MAX_SLOTS = 24;

    protected ?string $usageColumn = 'grade_id';

    protected string $resource = 'grades';

    protected array $columns = ['Bidang › Kategori', 'Harga', 'Per Pertemuan', 'Pengajar'];

    protected array $with = ['category:id,name,subject_id', 'category.subject:id,name', 'teachers:id,name'];

    public function __construct(private readonly ScheduleRoutineService $routines)
    {
    }

    protected function extraRules(Request $request, string $branchId, Model $item): array
    {
        $package = $request->input('billing') === ScheduleGrade::BILLING_PACKAGE;
        $inBranch = fn (string $table) => Rule::exists($table, 'id')->where('branch_id', $branchId)->where('status', 'active');

        return [
            'billing' => ['required', Rule::in(array_keys(ScheduleGrade::BILLING_LABELS))],
            'price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            // Bulanan: pertemuan per bulan (maks. 31). Paket: jumlah pertemuan dalam satu paket.
            'meetings_per_month' => ['required', 'integer', 'min:1', 'max:'.($package ? 200 : 31)],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:600'],
            'teacher_percent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'teacher_ids' => ['nullable', 'array'],
            // Pengajar harus dari branch yang sama dengan grade-nya.
            'teacher_ids.*' => ['string', $inBranch('schedule_teachers')],

            // Jam buka: tiap baris dipecah sendiri mengikuti durasi grade. Baris kosong dilewati,
            // baris setengah terisi ditolak di createSlots() dengan pesan yang menyebut nomor barisnya.
            'slot_month' => ['nullable', 'date_format:Y-m'],
            'slot_capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'slots' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'slots.*.day' => ['nullable', 'integer', Rule::in(array_keys(ScheduleRoutine::DAYS))],
            'slots.*.from' => ['nullable', 'date_format:H:i'],
            'slots.*.until' => ['nullable', 'date_format:H:i'],
            'slots.*.teacher' => ['nullable', 'string', $inBranch('schedule_teachers')],
            'slots.*.room' => ['nullable', 'string', $inBranch('schedule_rooms')],
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
            'slot_month' => 'bulan jam buka',
            'slot_capacity' => 'kapasitas murid',
        ];
    }

    protected function saved(Request $request, Model $item, array $data): void
    {
        $item->teachers()->sync($data['teacher_ids'] ?? []);
        $this->createSlots($item, $data);
    }

    /**
     * Jam buka -> slot. Tiap baris (hari, jam mulai, jam selesai, pengajar, ruangan) dipecah sesuai
     * durasi grade, lalu tiap potongan disimpan sebagai jadwal biasa lewat service yang sama.
     * Muridnya kosong dulu dan diisi lewat tombol Isi Murid; slot kosong belum membuat sesi.
     */
    private function createSlots(ScheduleGrade $grade, array $data): void
    {
        $rows = collect($data['slots'] ?? [])
            ->filter(fn ($row) => collect($row)->contains(fn ($value) => filled($value)))
            ->values();

        if ($rows->isEmpty()) {
            return;
        }

        if ($grade->isPackage()) {
            throw ValidationException::withMessages(['slots' => 'Grade paket memakai pola tanggal, bukan jam buka. Kosongkan baris jamnya.']);
        }

        $teacherIds = $data['teacher_ids'] ?? [];
        $capacity = (int) ($data['slot_capacity'] ?? 1) ?: 1;
        $month = Carbon::createFromFormat('!Y-m', $data['slot_month'] ?? now()->format('Y-m'));

        foreach ($rows as $index => $row) {
            $number = $index + 1;

            foreach (['day' => 'hari', 'from' => 'jam mulai', 'until' => 'jam selesai', 'teacher' => 'pengajar', 'room' => 'ruangan'] as $key => $label) {
                if (blank($row[$key] ?? null)) {
                    throw ValidationException::withMessages(["slots.{$index}.{$key}" => "Baris jam ke-{$number} belum lengkap: {$label} masih kosong."]);
                }
            }

            if (! in_array($row['teacher'], $teacherIds, true)) {
                throw ValidationException::withMessages(["slots.{$index}.teacher" => "Pengajar di baris jam ke-{$number} belum dicentang di daftar pengajar grade ini."]);
            }

            $times = $this->routines->slotTimes($row['from'], $row['until'], $grade->duration_minutes, self::MAX_SLOTS);

            if ($times === []) {
                throw ValidationException::withMessages(["slots.{$index}.until" => "Baris jam ke-{$number} belum cukup untuk satu pertemuan {$grade->duration_minutes} menit."]);
            }

            foreach ($times as $time) {
                $routine = new ScheduleRoutine();
                $routine->forceFill(['branch_id' => $grade->branch_id]);

                try {
                    $this->routines->save($routine, [
                        'grade_id' => $grade->getKey(),
                        'teacher_id' => $row['teacher'],
                        'room_id' => $row['room'],
                        'day_of_week' => (int) $row['day'],
                        'capacity' => $capacity,
                        'duration_minutes' => $grade->duration_minutes,
                        'start_time' => $time,
                        'pattern' => null,
                        'starts_on' => $month->toDateString(),
                        'ends_on' => $month->copy()->endOfMonth()->toDateString(),
                        'status' => ScheduleRoutine::STATUS_ACTIVE,
                    ], []);
                } catch (RuntimeException $e) {
                    // Transaksi induk ikut dibatalkan, jadi grade & slot lain tidak tertinggal separuh jadi.
                    throw ValidationException::withMessages(["slots.{$index}.from" => "Baris jam ke-{$number} pukul {$time}: ".$e->getMessage()]);
                }
            }
        }
    }

    /** Pengajar, ruangan, dan jam buka dipilih di sini supaya satu grade selesai dalam satu formulir. */
    protected function formData(Request $request, Model $item): array
    {
        $user = $request->user();
        $scoped = fn ($query) => $query->accessibleBy($user)
            ->when($item->exists, fn ($q) => $q->where('branch_id', $item->branch_id))
            // Superadmin membuat grade baru: branch belum tentu, jadi pilihannya menyusul lewat Edit.
            ->when(! $item->exists && $user->isPlatformTeam(), fn ($q) => $q->whereRaw('1 = 0'));

        return [
            'teachers' => $scoped(ScheduleTeacher::query())->with('branch:id,name')->orderBy('name')->get(),
            'rooms' => $scoped(ScheduleRoom::query())->orderBy('name')->get(['id', 'name', 'branch_id', 'status']),
            'selectedTeachers' => collect(old('teacher_ids', $item->exists ? $item->teachers()->pluck('schedule_teachers.id')->all() : [])),
            'slotCount' => $item->exists ? ScheduleRoutine::where('grade_id', $item->getKey())->whereNull('pattern')->count() : 0,
        ];
    }
}
