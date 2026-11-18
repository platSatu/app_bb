<?php

namespace App\Http\Controllers\Schedule;

use App\Models\ScheduleGrade;
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

        return [
            'billing' => ['required', Rule::in(array_keys(ScheduleGrade::BILLING_LABELS))],
            'price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            // Bulanan: pertemuan per bulan (maks. 31). Paket: jumlah pertemuan dalam satu paket.
            'meetings_per_month' => ['required', 'integer', 'min:1', 'max:'.($package ? 200 : 31)],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:600'],
            'teacher_percent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            // Satu grade diampu satu pengajar. Disimpan lewat tabel penghubung yang sudah ada,
            // jadi tidak perlu migrasi dan $grade->teachers() tetap terbaca di tempat lain.
            'teacher_id' => ['nullable', 'string', Rule::exists('schedule_teachers', 'id')->where('branch_id', $branchId)->where('status', 'active')],

            // Jam buka: tiap baris dipecah sendiri mengikuti durasi grade. Pengajar ikut grade,
            // ruangan ikut Bidang, jadi barisnya cukup hari + jam mulai + jam selesai.
            'slot_month' => ['nullable', 'date_format:Y-m'],
            'slot_capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'slots' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'slots.*.day' => ['nullable', 'integer', Rule::in(array_keys(ScheduleRoutine::DAYS))],
            'slots.*.from' => ['nullable', 'date_format:H:i'],
            'slots.*.until' => ['nullable', 'date_format:H:i'],
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
            'teacher_id' => 'pengajar',
            'slot_month' => 'bulan jam buka',
            'slot_capacity' => 'kapasitas murid',
        ];
    }

    protected function saved(Request $request, Model $item, array $data): void
    {
        $item->teachers()->sync(array_filter([$data['teacher_id'] ?? null]));
        $this->createSlots($item, $data);
    }

    /**
     * Jam buka -> slot. Tiap baris (hari, jam mulai, jam selesai) dipecah sesuai durasi grade, lalu
     * tiap potongan disimpan sebagai jadwal biasa lewat service yang sama. Pengajarnya pengajar grade
     * ini, ruangannya ruangan Bidang -- keduanya sudah ditentukan dari awal rangkaian, jadi tidak
     * ditanyakan ulang. Murid dikosongkan dulu; slot kosong belum membuat sesi.
     */
    private function createSlots(ScheduleGrade $grade, array $data): void
    {
        $rows = collect($data['slots'] ?? [])
            ->filter(fn ($row) => filled($row['from'] ?? null) || filled($row['until'] ?? null))
            ->values();

        if ($rows->isEmpty()) {
            return;
        }

        if ($grade->isPackage()) {
            throw ValidationException::withMessages(['slots' => 'Grade paket memakai pola tanggal, bukan jam buka. Kosongkan baris jamnya.']);
        }

        $teacherId = $data['teacher_id'] ?? null;

        if (! $teacherId) {
            throw ValidationException::withMessages(['teacher_id' => 'Pilih pengajarnya dulu sebelum mengisi jam buka.']);
        }

        $grade->loadMissing('category.subject');
        $roomId = $grade->category?->subject?->room_id;

        if (! $roomId) {
            throw ValidationException::withMessages(['slots' => 'Bidang untuk grade ini belum punya ruangan. Isi dulu ruangannya di menu Bidang, baru jam bukanya bisa dibuat.']);
        }

        $capacity = (int) ($data['slot_capacity'] ?? 1) ?: 1;
        $month = Carbon::createFromFormat('!Y-m', $data['slot_month'] ?? now()->format('Y-m'));

        foreach ($rows as $index => $row) {
            $number = $index + 1;

            foreach (['from' => 'jam mulai', 'until' => 'jam selesai'] as $key => $label) {
                if (blank($row[$key] ?? null)) {
                    throw ValidationException::withMessages(["slots.{$index}.{$key}" => "Baris jam ke-{$number} belum lengkap: {$label} masih kosong."]);
                }
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
                        'teacher_id' => $teacherId,
                        'room_id' => $roomId,
                        'day_of_week' => (int) ($row['day'] ?? 1),
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

    /** Pengajar & jam buka dipilih di sini supaya satu grade selesai dalam satu formulir. */
    protected function formData(Request $request, Model $item): array
    {
        $user = $request->user();

        return [
            'teachers' => ScheduleTeacher::accessibleBy($user)
                ->with('branch:id,name')
                ->when($item->exists, fn ($q) => $q->where('branch_id', $item->branch_id))
                // Superadmin membuat grade baru: branch belum tentu, jadi pilihannya menyusul lewat Edit.
                ->when(! $item->exists && $user->isPlatformTeam(), fn ($q) => $q->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get(),
            'selectedTeacher' => $item->exists ? $item->teachers()->value('schedule_teachers.id') : null,
            'slotCount' => $item->exists ? ScheduleRoutine::where('grade_id', $item->getKey())->whereNull('pattern')->count() : 0,
        ];
    }
}
