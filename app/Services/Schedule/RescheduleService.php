<?php

namespace App\Services\Schedule;

use App\Jobs\SendWaNotification;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleRescheduleRequest;
use App\Models\ScheduleRoutine;
use App\Models\ScheduleSession;
use App\Models\User;
use App\Support\ScheduleBotText;
use App\Support\ScheduleNotice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Izin & ganti hari dari murid / orang tua, dipakai chatbot WhatsApp dan halaman Schedules › Ganti Hari.
 * Tidak membuat aturan baru: memindah sesi lewat ScheduleSessionService::move() dan membuat sesi pengganti lewat
 * ScheduleSessionService::makeup(), jadi bentrok, jatah bulan, dan honor dihitung persis seperti di website.
 *  - Kelas privat (murid satu-satunya di sesi itu): disetujui = sesinya dipindah.
 *  - Kelas grup: disetujui = dicatat; sesi pengganti dibuat otomatis begitu sesi asli ditutup (afterSessionCompleted),
 *    karena di website sesi pengganti baru bisa dibuat setelah sesi selesai.
 * Semua perubahan memakai lockForUpdate supaya balasan ganda / dua admin bersamaan tidak membuat data dobel.
 */
class RescheduleService
{
    /** Jadwal kosong dicari sampai sekian hari ke depan. */
    public const SEARCH_DAYS = 14;

    public const MAX_SLOTS = 5;

    /** Pengajuan tidak boleh untuk jam yang terlalu dekat, supaya admin sempat menyetujui. */
    private const MIN_LEAD_MINUTES = 60;

    private const EXCUSE_NOTE = 'Izin via WhatsApp: ';

    public function __construct(private readonly ScheduleConflict $conflict, private readonly ScheduleSessionService $sessions)
    {
    }

    /** Murid / orang tua mengonfirmasi hadir. Status absensi tidak diubah (tetap diisi pengajar saat les). */
    public function confirm(ScheduleAttendance $attendance): void
    {
        DB::transaction(function () use ($attendance) {
            $locked = $this->lockOpen($attendance);
            $locked->forceFill(['response' => ScheduleAttendance::RESPONSE_CONFIRMED, 'responded_at' => now(), 'responded_via' => 'whatsapp'])->save();
        });
    }

    /** Berhalangan: tercatat Izin + alasannya (sama seperti admin memilih Izin), jadi tidak ditagih. */
    public function excuse(ScheduleAttendance $attendance, string $reason): void
    {
        DB::transaction(function () use ($attendance, $reason) {
            $locked = $this->lockOpen($attendance);
            $locked->forceFill([
                'status' => ScheduleAttendance::STATUS_EXCUSED,
                'response' => ScheduleAttendance::RESPONSE_EXCUSED,
                'responded_at' => now(),
                'responded_via' => 'whatsapp',
                // Catatan yang sudah diisi admin tidak ditimpa.
                'notes' => $locked->notes ?: mb_substr(self::EXCUSE_NOTE.$reason, 0, 255),
            ])->save();
        });
    }

    /** Masih boleh mengajukan ganti hari: sesi terjadwal & belum mulai, murid izin, belum ada pengajuan yang berjalan. */
    public function canRequest(ScheduleAttendance $attendance): bool
    {
        $session = $attendance->session;

        return $session && $session->status === ScheduleSession::STATUS_SCHEDULED && $session->start_at->isFuture()
            && $attendance->status === ScheduleAttendance::STATUS_EXCUSED && ! $attendance->makeup_session_id
            && ! ScheduleRescheduleRequest::where('attendance_id', $attendance->getKey())
                ->whereIn('status', [ScheduleRescheduleRequest::STATUS_PENDING, ScheduleRescheduleRequest::STATUS_APPROVED])->exists();
    }

    /**
     * Jadwal pengajar yang sama yang masih kosong (slot jadwal rutin aktif tanpa bentrok pengajar, ruangan, maupun murid),
     * setelah sesi aslinya selesai, sampai SEARCH_DAYS hari ke depan. Paling banyak MAX_SLOTS, urut paling dekat.
     *
     * @return list<array{start:string,end:string,teacher_id:string,room_id:string}>
     */
    public function availableSlots(ScheduleAttendance $attendance): array
    {
        $session = $attendance->session;

        if (! $session || $session->status !== ScheduleSession::STATUS_SCHEDULED) {
            return [];
        }

        $private = $this->isPrivate($session);
        $minutes = max(15, (int) $session->start_at->diffInMinutes($session->end_at));
        $from = $session->end_at->copy()->max(now()->addMinutes(self::MIN_LEAD_MINUTES));
        $until = now()->addDays(self::SEARCH_DAYS)->endOfDay();

        $routines = ScheduleRoutine::where('branch_id', $session->branch_id)
            ->where('teacher_id', $session->teacher_id)
            ->where('status', ScheduleRoutine::STATUS_ACTIVE)
            ->whereNull('pattern')
            ->whereDate('starts_on', '<=', $until)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from))
            ->orderBy('start_time')
            ->get(['id', 'room_id', 'day_of_week', 'start_time', 'starts_on', 'ends_on']);

        $slots = [];

        for ($day = $from->copy()->startOfDay(); $day->lte($until) && count($slots) < self::MAX_SLOTS; $day->addDay()) {
            foreach ($routines->where('day_of_week', $day->dayOfWeekIso) as $routine) {
                if ($day->lt($routine->starts_on->copy()->startOfDay()) || ($routine->ends_on && $day->gt($routine->ends_on->copy()->endOfDay()))) {
                    continue;
                }

                $start = $day->copy()->setTimeFromTimeString((string) $routine->start_time);
                $end = $start->copy()->addMinutes($minutes);
                $key = $start->format('Y-m-d H:i');

                if ($start->lt($from) || isset($slots[$key]) || ! $this->slotFree($session, $attendance, $private, $start, $end, $routine->room_id)) {
                    continue;
                }

                $slots[$key] = ['start' => $key, 'end' => $end->format('Y-m-d H:i'), 'teacher_id' => $session->teacher_id, 'room_id' => $routine->room_id];

                if (count($slots) >= self::MAX_SLOTS) {
                    break;
                }
            }
        }

        return array_values($slots);
    }

    /**
     * Ajukan ganti hari ke slot pilihan. Slotnya dihitung ulang di sini (bukan dipercaya dari percakapan),
     * jadi pilihan yang sudah terisi atau tidak sah ditolak.
     *
     * @param array{start:string,teacher_id:string,room_id:string} $slot
     */
    public function submit(ScheduleAttendance $attendance, array $slot, ?string $reason): ScheduleRescheduleRequest
    {
        return DB::transaction(function () use ($attendance, $slot, $reason) {
            $locked = ScheduleAttendance::with('session')->whereKey($attendance->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->canRequest($locked)) {
                throw new RuntimeException('Pengajuan untuk jadwal ini sudah tidak bisa dibuat.');
            }

            $fresh = collect($this->availableSlots($locked))->first(fn ($s) => $s['start'] === ($slot['start'] ?? null)
                && $s['teacher_id'] === ($slot['teacher_id'] ?? null) && $s['room_id'] === ($slot['room_id'] ?? null));

            if (! $fresh) {
                throw new RuntimeException('Jadwal yang dipilih sudah tidak tersedia.');
            }

            $request = new ScheduleRescheduleRequest();
            $request->forceFill([
                'branch_id' => $locked->session->branch_id,
                'session_id' => $locked->session_id,
                'attendance_id' => $locked->getKey(),
                'student_id' => $locked->student_id,
                'reason' => $reason !== null ? mb_substr(trim($reason), 0, 255) : null,
                'slot_start_at' => Carbon::parse($fresh['start']),
                'slot_end_at' => Carbon::parse($fresh['end']),
                'slot_teacher_id' => $fresh['teacher_id'],
                'slot_room_id' => $fresh['room_id'],
                'mode' => $this->isPrivate($locked->session) ? ScheduleRescheduleRequest::MODE_MOVE : ScheduleRescheduleRequest::MODE_MAKEUP,
                'status' => ScheduleRescheduleRequest::STATUS_PENDING,
                'requested_via' => 'whatsapp',
            ])->save();

            return $request;
        });
    }

    /** $via: whatsapp | web. Orang tua dikabari lewat WhatsApp setelah tersimpan. */
    public function approve(ScheduleRescheduleRequest $request, User $actor, string $via): ScheduleRescheduleRequest
    {
        $approved = DB::transaction(function () use ($request, $actor, $via) {
            $locked = $this->lockPending($request);
            $attendance = ScheduleAttendance::with('session')->whereKey($locked->attendance_id)->lockForUpdate()->firstOrFail();
            $session = $attendance->session;

            if ($session->status === ScheduleSession::STATUS_CANCELLED) {
                throw new RuntimeException('Sesi lamanya sudah dibatalkan.');
            }

            if ($locked->mode === ScheduleRescheduleRequest::MODE_MOVE) {
                if ($session->status !== ScheduleSession::STATUS_SCHEDULED || ! $session->start_at->isFuture()) {
                    throw new RuntimeException('Sesi lamanya sudah dimulai atau selesai, jadi tidak bisa dipindah lagi. Buat sesi pengganti lewat website kalau perlu.');
                }

                if (! $this->isPrivate($session)) {
                    throw new RuntimeException('Kelas ini sekarang berisi lebih dari satu murid, jadi sesinya tidak bisa dipindah. Atur sesi pengganti lewat website.');
                }

                // Aturan sama dengan tombol Pindah: bentrok & tanggal dobel dicek di sana.
                $this->sessions->move($session, $locked->slot_start_at->toDateString(), $locked->slot_start_at->format('H:i'), $locked->slot_teacher_id, $locked->slot_room_id, notifyStudents: false);

                // Murid hadir di jadwal baru: absensinya kembali "belum diisi".
                $attendance->refresh()->forceFill([
                    'status' => ScheduleAttendance::STATUS_PENDING,
                    'response' => null,
                    'responded_at' => null,
                    'responded_via' => null,
                    'notes' => str_starts_with((string) $attendance->notes, self::EXCUSE_NOTE) ? null : $attendance->notes,
                ])->save();

                $status = ScheduleRescheduleRequest::STATUS_DONE;
            } else {
                if ($message = $this->conflict->forSession($session->branch_id, $locked->slot_start_at, $locked->slot_end_at, $locked->slot_teacher_id, $locked->slot_room_id, [$locked->student_id])) {
                    throw new RuntimeException($message);
                }

                $status = ScheduleRescheduleRequest::STATUS_APPROVED;
            }

            $locked->forceFill(['status' => $status, 'reviewed_by' => $actor->getKey(), 'reviewed_via' => $via, 'reviewed_at' => now()])->save();

            return $locked;
        });

        $this->tellParent($approved, ScheduleBotText::approvedToParent($this->loaded($approved)));

        // Kelas grup yang sesi lamanya sudah ditutup sebelum disetujui: sesi pengganti langsung dibuat sekarang.
        if ($approved->status === ScheduleRescheduleRequest::STATUS_APPROVED && $approved->session->status === ScheduleSession::STATUS_DONE) {
            $this->afterSessionCompleted($approved->session);
        }

        return $approved->refresh();
    }

    public function reject(ScheduleRescheduleRequest $request, string $reason, User $actor, string $via): ScheduleRescheduleRequest
    {
        $rejected = DB::transaction(function () use ($request, $reason, $actor, $via) {
            $locked = $this->lockPending($request);
            $locked->forceFill([
                'status' => ScheduleRescheduleRequest::STATUS_REJECTED,
                'reject_reason' => mb_substr(trim($reason), 0, 255),
                'reviewed_by' => $actor->getKey(),
                'reviewed_via' => $via,
                'reviewed_at' => now(),
            ])->save();

            return $locked;
        });

        $this->tellParent($rejected, ScheduleBotText::rejectedToParent($this->loaded($rejected)));

        return $rejected;
    }

    /**
     * Dipanggil setelah absensi sesi disimpan (ScheduleSessionService::complete): pengajuan kelas grup yang sudah
     * disetujui dibuatkan sesi penggantinya lewat makeup(), sama seperti tombol Buat Sesi Pengganti.
     * Murid yang ternyata tidak Izin (mis. tetap datang) = pengajuannya batal. Jadwal keburu terisi = gagal, admin dikabari.
     */
    public function afterSessionCompleted(ScheduleSession $session): void
    {
        $requests = ScheduleRescheduleRequest::where('session_id', $session->getKey())
            ->where('status', ScheduleRescheduleRequest::STATUS_APPROVED)
            ->where('mode', ScheduleRescheduleRequest::MODE_MAKEUP)
            ->get();

        foreach ($requests as $request) {
            try {
                DB::transaction(function () use ($request) {
                    $locked = ScheduleRescheduleRequest::whereKey($request->getKey())->lockForUpdate()->first();

                    if (! $locked || $locked->status !== ScheduleRescheduleRequest::STATUS_APPROVED) {
                        return;
                    }

                    $attendance = ScheduleAttendance::with('session')->find($locked->attendance_id);

                    if (! $attendance || $attendance->status !== ScheduleAttendance::STATUS_EXCUSED || $attendance->makeup_session_id) {
                        $locked->forceFill(['status' => ScheduleRescheduleRequest::STATUS_CANCELLED, 'failure_reason' => 'Murid tidak tercatat Izin di sesi aslinya, atau sudah punya sesi pengganti.'])->save();

                        return;
                    }

                    $makeup = $this->sessions->makeup($attendance, $locked->slot_start_at->toDateString(), $locked->slot_start_at->format('H:i'), $locked->slot_teacher_id, $locked->slot_room_id);
                    $locked->forceFill(['status' => ScheduleRescheduleRequest::STATUS_DONE, 'makeup_session_id' => $makeup->getKey()])->save();
                });
            } catch (RuntimeException $e) {
                $request->forceFill(['status' => ScheduleRescheduleRequest::STATUS_FAILED, 'failure_reason' => mb_substr($e->getMessage(), 0, 500)])->save();
                $this->tellAdmin($request, ScheduleBotText::adminMakeupFailed($this->loaded($request), $e->getMessage()));
                Log::info('[JADWAL WA] Sesi pengganti gagal dibuat', ['request' => $request->getKey(), 'reason' => $e->getMessage()]);
            }
        }
    }

    public function isPrivate(ScheduleSession $session): bool
    {
        return $session->attendances()->count() === 1;
    }

    /** Nomor WhatsApp admin branch = nomor owner (sama dengan laporan absen masuk). */
    public static function adminPhone(?\App\Models\UserBranch $branch): string
    {
        return User::normalizePhone($branch?->user?->handphone);
    }

    private function slotFree(ScheduleSession $session, ScheduleAttendance $attendance, bool $private, Carbon $start, Carbon $end, string $roomId): bool
    {
        // Pindah sesi privat: satu jadwal rutin tidak boleh punya dua sesi di tanggal yang sama (aturan move()).
        if ($private && $session->routine_id && ScheduleSession::where('routine_id', $session->routine_id)
            ->whereDate('date', $start->toDateString())->whereKeyNot($session->getKey())->exists()) {
            return false;
        }

        return $this->conflict->forSession($session->branch_id, $start, $end, $session->teacher_id, $roomId, [$attendance->student_id], $private ? $session->getKey() : null) === null;
    }

    /** Absensi yang masih bisa dijawab: sesinya terjadwal & belum dimulai. */
    private function lockOpen(ScheduleAttendance $attendance): ScheduleAttendance
    {
        $locked = ScheduleAttendance::with('session')->whereKey($attendance->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->session->status !== ScheduleSession::STATUS_SCHEDULED || ! $locked->session->start_at->isFuture()) {
            throw new RuntimeException('Jadwal ini sudah dimulai atau berubah.');
        }

        return $locked;
    }

    private function lockPending(ScheduleRescheduleRequest $request): ScheduleRescheduleRequest
    {
        $locked = ScheduleRescheduleRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

        if (! $locked->isPending()) {
            throw new RuntimeException('Pengajuan ini sudah diproses sebelumnya.');
        }

        return $locked;
    }

    private function loaded(ScheduleRescheduleRequest $request): ScheduleRescheduleRequest
    {
        return $request->load(['student', 'slotTeacher:id,name', 'session.grade.category.subject', 'branch.user:id,handphone']);
    }

    private function tellParent(ScheduleRescheduleRequest $request, string $text): void
    {
        SendWaNotification::queue($request->branch_id, ScheduleNotice::BOT, $request->student?->phone, $text);
    }

    private function tellAdmin(ScheduleRescheduleRequest $request, string $text): void
    {
        SendWaNotification::queue($request->branch_id, ScheduleNotice::BOT, self::adminPhone($request->branch), $text);
    }
}
