<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InPortal;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleSession;
use App\Models\ScheduleStudent;
use App\Models\ScheduleTeacher;
use App\Models\UserBranch;
use App\Services\Schedule\ScheduleSessionService;
use App\Services\WhatsApp\Schedule\TeacherBot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Portal pengajar: jadwal mengajar lintas branch & absen murid (hanya sesi miliknya sendiri). */
class TeachingController extends Controller
{
    use InPortal;

    public function __construct(private readonly ScheduleSessionService $sessions, private readonly TeacherBot $teacherBot)
    {
    }

    /** Sesi yang sudah dimulai tapi belum diabsen, 60 hari terakhir. */
    public static function needsAttendance(array $teacherIds): Builder
    {
        return ScheduleSession::whereIn('teacher_id', $teacherIds)
            ->where('status', ScheduleSession::STATUS_SCHEDULED)
            ->whereBetween('start_at', [now()->subDays(60), now()]);
    }

    public function index(Request $request): View
    {
        $teachers = $this->teachers($request->user());
        $tabs = $this->tabs($teachers, fn ($t) => $t->branch?->name ?? '-');
        $active = $this->selected($request, 'branch', $tabs);
        $week = (rescue(fn () => $request->date('minggu', 'Y-m-d'), null, false) ?? today())->startOfWeek();

        $sessions = ScheduleSession::with([...self::SESSION_WITH, 'attendances.student:id,name'])
            ->whereIn('teacher_id', $teachers->modelKeys())
            ->whereBetween('start_at', [$week, $week->copy()->endOfWeek()])
            ->orderBy('start_at')
            ->get();

        return view('portal.teaching.index', [
            // Bentrok dicek dari semua branch, walau yang tampil hanya satu tab.
            'clashes' => $this->clashes($sessions),
            'sessions' => $active ? $sessions->where('teacher_id', $active)->values() : $sessions,
            'tabs' => $tabs,
            'active' => $active,
            'week' => $week,
            'showBranch' => count($tabs) > 1 && ! $active,
            'colors' => $this->branchColors($teachers->pluck('branch_id')),
        ]);
    }

    public function pending(Request $request): View
    {
        $teachers = $this->teachers($request->user());
        $done = $request->query('lihat') === 'selesai';
        $ids = $teachers->modelKeys();

        $query = $done
            ? ScheduleSession::whereIn('teacher_id', $ids)->where('status', ScheduleSession::STATUS_DONE)->where('start_at', '>=', now()->subDays(30))->latest('start_at')
            : self::needsAttendance($teachers->where('status', ScheduleTeacher::STATUS_ACTIVE)->modelKeys())->orderBy('start_at');

        return view('portal.teaching.pending', [
            'sessions' => $query->with([...self::SESSION_WITH, 'attendances.student:id,name'])->paginate(20)->withQueryString(),
            'done' => $done,
            'toFill' => self::needsAttendance($teachers->where('status', ScheduleTeacher::STATUS_ACTIVE)->modelKeys())->count(),
            'showBranch' => $teachers->count() > 1,
            'colors' => $this->branchColors($teachers->pluck('branch_id')),
        ]);
    }

    public function show(Request $request, string $session): View
    {
        $model = $this->find($request, $session);

        return view('portal.teaching.show', ['session' => $model, 'canFill' => $this->canFill($request, $model)]);
    }

    public function save(Request $request, string $session): RedirectResponse
    {
        $model = $this->find($request, $session);
        abort_unless($this->canFill($request, $model), 403);

        $data = $request->validate([
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', Rule::in([ScheduleAttendance::STATUS_PRESENT, ScheduleAttendance::STATUS_ABSENT, ScheduleAttendance::STATUS_EXCUSED])],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:255'],
        ], ['statuses.required' => 'Pilih kehadiran setiap murid dulu ya.']);

        try {
            $this->sessions->complete($model, $data['statuses'], $data['notes'] ?? null, $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('portal.absen')->with('success', 'Terima kasih, absen '.$model->grade?->name.' tanggal '.$model->start_at->translatedFormat('d M').' sudah tersimpan.');
    }

    /** Halaman scan: sekali buka kamera, scan QR tempat (absen pengajar) lalu kartu murid di sesi hari ini. */
    public function scan(Request $request): View
    {
        $ids = $this->activeTeachers($request)->modelKeys();
        abort_if($ids === [], 403);

        return view('portal.teaching.scan', [
            'sessions' => ScheduleSessionService::scanToday($ids)->with([...self::SESSION_WITH, 'attendances.student:id,name'])->orderBy('start_at')->get(),
        ]);
    }

    /** Satu kali scan: QR tempat = absen pengajar, kartu murid = absen murid. QR palsu / di luar haknya ditolak & dicatat di log. */
    public function checkIn(Request $request): JsonResponse
    {
        $teachers = $this->activeTeachers($request);
        $code = (string) $request->input('code');

        if ($teachers->isEmpty()) {
            return $this->rejectScan($request, 'Akun Anda bukan pengajar aktif.', 'bukan pengajar aktif');
        }

        try {
            if (str_starts_with($code, 'BBP.')) {
                $branchId = UserBranch::idFromPlaceQr($code);
                $ids = $branchId ? $teachers->where('branch_id', $branchId)->modelKeys() : [];

                if ($ids === []) {
                    return $this->rejectScan($request, 'QR tempat ini tidak berlaku untuk Anda. Pastikan memakai QR terbaru di tempat Anda mengajar.', 'QR tempat tidak sah / branch lain', $branchId);
                }

                [$problem, $distance] = $this->placeCheck($request, UserBranch::findOrFail($branchId));

                if ($problem) {
                    return $this->rejectScan($request, $problem, 'lokasi di luar tempat les'.($distance !== null ? " ({$distance} m)" : ''), $branchId);
                }

                $sessions = $this->sessions->teacherCheckIn($branchId, $ids, $distance);
                // Chatbot WhatsApp Jadwal: sesi yang sudah dimulai langsung dikirimi daftar absen murid (sisanya menyusul saat mulai).
                $sentToWa = $sessions->filter(fn ($session) => $this->teacherBot->promptAttendance($session))->isNotEmpty();

                return response()->json([
                    'message' => 'Terima kasih, Anda tercatat hadir pukul '.now()->format('H:i').' untuk '.$sessions->count().' sesi. '
                        .($sentToWa ? 'Daftar murid juga kami kirim ke WhatsApp Anda, bisa diabsen dari sana atau scan kartu murid.' : 'Sekarang scan kartu murid ya.'),
                    'reload' => true,
                ]);
            }

            $studentId = ScheduleStudent::idFromQr($code);

            if (! $studentId) {
                return $this->rejectScan($request, 'QR ini bukan kartu absen yang sah. Coba kartu yang lain ya.', 'QR murid tidak sah');
            }

            $attendance = $this->sessions->checkIn($studentId, $teachers->modelKeys(), $request->user());
        } catch (RuntimeException $e) {
            return $e->getCode() === 403
                ? $this->rejectScan($request, $e->getMessage(), 'murid di luar sesi pengajar', $studentId ?? null)
                : response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $attendance->student?->name.' hadir, tercatat pukul '.$attendance->scanned_at->format('H:i').'.',
            'session' => $attendance->session_id,
        ]);
    }

    /**
     * Cek GPS absen datang: kalau owner sudah mengatur titik lokasi branch, HP pengajar harus berada dalam
     * UserBranch::PLACE_RADIUS_METERS dari titik itu (dikurangi sedikit toleransi akurasi). Tanpa titik lokasi = tidak dicek.
     *
     * @return array{0: ?string, 1: ?int} [pesan penolakan atau null, jarak meter]
     */
    private function placeCheck(Request $request, UserBranch $branch): array
    {
        if (! $branch->hasPlaceLocation()) {
            return [null, null];
        }

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $accuracy = (float) $request->input('accuracy', 9999);

        if (! is_numeric($lat) || ! is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return ['Izinkan akses lokasi (GPS) di browser dulu ya. Absen di tempat les perlu memastikan Anda berada di lokasi.', null];
        }

        if ($accuracy > UserBranch::PLACE_MAX_ACCURACY_METERS) {
            return ['Lokasi HP Anda belum akurat (sekitar '.(int) round($accuracy).' m). Nyalakan GPS / mode akurasi tinggi, tunggu sebentar, lalu scan lagi.', null];
        }

        $distance = $branch->distanceTo((float) $lat, (float) $lng);

        if ($distance - min($accuracy, 50) > UserBranch::PLACE_RADIUS_METERS) {
            return ['Anda terdeteksi sekitar '.$distance.' m dari tempat les. Absen hanya bisa dilakukan di lokasi ya.', $distance];
        }

        return [null, $distance];
    }

    private function rejectScan(Request $request, string $message, string $reason, ?string $target = null): JsonResponse
    {
        Log::warning('[SCAN ABSEN] Ditolak: '.$reason, ['user' => $request->user()->getKey(), 'target' => $target, 'ip' => $request->ip()]);

        return response()->json(['message' => $message], 403);
    }

    private function activeTeachers(Request $request): Collection
    {
        return $this->teachers($request->user())->where('status', ScheduleTeacher::STATUS_ACTIVE);
    }

    /** Pengajar hanya mengisi sesi miliknya yang masih terjadwal & sudah dimulai. Koreksi setelah selesai lewat admin. */
    private function canFill(Request $request, ScheduleSession $session): bool
    {
        return $session->status === ScheduleSession::STATUS_SCHEDULED
            && $session->start_at->isPast()
            && ScheduleTeacher::whereKey($session->teacher_id)->where('user_id', $request->user()->getKey())->active()->exists();
    }

    private function find(Request $request, string $id): ScheduleSession
    {
        return ScheduleSession::with([...self::SESSION_WITH, 'attendances.student:id,name'])
            ->whereIn('teacher_id', ScheduleTeacher::where('user_id', $request->user()->getKey())->select('id'))
            ->whereIn('branch_id', \App\Services\Subscription\AppAccess::activeBranches('schedules'))
            ->findOrFail($id);
    }

    /** Id sesi yang waktunya bertabrakan dengan sesi lain milik akun yang sama. */
    private function clashes($sessions): array
    {
        $live = $sessions->where('status', '!=', ScheduleSession::STATUS_CANCELLED)->values();
        $ids = [];

        foreach ($live as $i => $a) {
            foreach ($live->slice($i + 1) as $b) {
                if ($a->start_at < $b->end_at && $b->start_at < $a->end_at) {
                    $ids[$a->id] = $ids[$b->id] = true;
                }
            }
        }

        return $ids;
    }
}
