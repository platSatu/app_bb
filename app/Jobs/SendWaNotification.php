<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleSession;
use App\Models\ScheduleStudent;
use App\Models\ScheduleTeacher;
use App\Models\TicketOrder;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaDevice;
use App\Services\WhatsApp\WaGateway;
use App\Support\ScheduleNotice;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Notifikasi WhatsApp otomatis (isi sama dengan email) lewat nomor WhatsApp branch.
 * Hanya dikirim kalau sakelar jenisnya nyala (Invoice & Tiket di Settings › Branch, Jadwal di Schedules › Notifikasi), paket WhatsApp aktif & ada nomor terhubung.
 * Dikirim di antrean supaya pembayaran/penyimpanan tidak ikut menunggu; gagal kirim tidak mengganggu proses utama.
 */
class SendWaNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 60;

    public function __construct(public string $branchId, public string $phone, public string $text, public string $kind)
    {
        $this->afterCommit();
    }

    /** Antrekan pesan; false kalau notifikasi jenis ini tidak aktif atau nomor tujuan kosong. */
    public static function queue(?string $branchId, string $kind, ?string $phone, string $text): bool
    {
        $phone = User::normalizePhone($phone);

        if (! $branchId || $phone === '' || ! UserBranch::find($branchId)?->waNotifies($kind) || ! WaDevice::connectedByBranch($branchId)) {
            return false;
        }

        dispatch(new self($branchId, $phone, $text, $kind));

        return true;
    }

    /** $kind: issued | reminder | paid, sama dengan email invoice. */
    public static function invoice(Invoice $invoice, string $kind): bool
    {
        $rp = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
        $company = $invoice->company?->name ?? config('app.name');

        $text = $kind === 'paid'
            ? "Halo {$invoice->customer_name}, terima kasih! Pembayaran invoice {$invoice->number} sudah kami terima.\n\n"
                ."{$invoice->description}\nTotal dibayar: {$rp($invoice->paid_amount)}\n"
                .'Dibayar pada '.$invoice->paid_at?->translatedFormat('d F Y H:i').".\n\nSimpan pesan ini sebagai bukti pembayaran.\n\nSalam,\n{$company}"
            : "Halo {$invoice->customer_name}, ".($kind === 'reminder' ? 'ini pengingat ramah untuk tagihan yang belum dibayar:' : "ada tagihan baru dari {$company}:")."\n\n"
                ."Nomor: {$invoice->number}\n{$invoice->description}: {$rp($invoice->amount)}\n"
                .($invoice->due_date ? 'Jatuh tempo: '.$invoice->due_date->translatedFormat('d F Y')."\n" : '')
                ."\nBayar lewat link ini (virtual account, QRIS, atau e-wallet):\n{$invoice->payUrl()}\n\n"
                ."Abaikan pesan ini kalau sudah membayar.\n\nSalam,\n{$company}";

        return self::queue($invoice->branch_id, 'invoice', $invoice->customer_phone, $text);
    }

    public static function tickets(TicketOrder $order): bool
    {
        $order->loadMissing('page.branch.company');

        $text = "Halo {$order->name}, terima kasih! Pembayaran untuk {$order->page->name} sudah kami terima.\n\n"
            ."Nomor pesanan: {$order->reference}\nTotal: Rp ".number_format((int) $order->total, 0, ',', '.')."\n\n"
            ."Lihat semua e-ticket di sini, lalu tunjukkan QR-nya saat registrasi ulang:\n{$order->url()}\n\n"
            ."Jangan bagikan link ini ke orang yang tidak berkepentingan ya, karena siapa pun yang memegang link bisa memakai tiketnya.\n\n"
            .'Salam,'."\n".($order->page->branch?->company?->name ?? config('app.name'));

        return self::queue($order->page->branch_id, 'ticket', $order->phone, $text);
    }

    /** Pengingat les ke murid / orang tua: H-1, atau ($soon) sesaat sebelum les dimulai. Relasi sesi sebaiknya sudah dimuat. */
    public static function lesson(ScheduleSession $session, ScheduleStudent $student, bool $soon = false): bool
    {
        return self::schedule($soon ? 'schedule_soon' : 'schedule', $session->branch_id, $student->phone, [
            ...self::sessionVars($session),
            'sapaan' => $student->guardian_name ?: $student->name,
            'murid' => $student->name,
            'kartu_absen' => $student->cardUrl(),
        ]);
    }

    /** Rekap H-1 untuk satu pengajar: semua pertemuannya besok di satu branch, lengkap dengan murid. */
    public static function teacherRecap(ScheduleTeacher $teacher, Collection $sessions): bool
    {
        $first = $sessions->first();

        return self::schedule('teacher_recap', $first->branch_id, $teacher->phone, [
            'branch' => $first->branch?->name ?? config('app.name'),
            'pengajar' => $teacher->name,
            'hari_tanggal' => $first->start_at->translatedFormat('l, d F Y'),
            'jumlah' => $sessions->count(),
            'daftar_sesi' => $sessions->map(fn (ScheduleSession $s) => self::sessionLine($s))->join("\n\n"),
        ]);
    }

    /**
     * Info perubahan ke pengajar (bawaan: pengajar sesi). Hanya untuk pertemuan yang -- sebelum atau sesudah berubah
     * ($was) -- jatuh dalam ScheduleNotice::CHANGE_DAYS hari ke depan, supaya menyusun jadwal bulan depan tidak membanjiri pengajar.
     */
    public static function teacherChange(ScheduleSession $session, string $change, ?ScheduleTeacher $teacher = null, ?CarbonInterface $was = null): bool
    {
        $soon = fn (?CarbonInterface $at) => $at && $at->between(now(), now()->addDays(ScheduleNotice::CHANGE_DAYS));
        $session->loadMissing(['branch:id,name', 'teacher:id,name,phone', 'room:id,name', 'grade.category.subject', 'attendances.student:id,name']);
        $teacher ??= $session->teacher;

        if (! $teacher || (! $soon($session->start_at) && ! $soon($was))) {
            return false;
        }

        return self::schedule('teacher_change', $session->branch_id, $teacher->phone, [
            'branch' => $session->branch?->name ?? config('app.name'),
            'pengajar' => $teacher->name,
            'perubahan' => $change,
            'detail_sesi' => self::sessionLine($session),
        ]);
    }

    /** Kartu murid discan hadir: kabar ke murid / orang tua, dan laporan ke admin (nomor WhatsApp owner branch). */
    public static function checkedIn(ScheduleAttendance $attendance): void
    {
        // load (bukan loadMissing): hasil scan memuat murid & sesi dengan kolom terbatas, jadi dimuat ulang lengkap.
        $attendance->load(['student', 'session.branch.user:id,handphone', 'session.teacher:id,name', 'session.room:id,name', 'session.grade.category.subject']);
        $session = $attendance->session;
        $vars = [...self::sessionVars($session), 'murid' => $attendance->student?->name, 'jam_hadir' => $attendance->scanned_at?->format('H.i')];

        self::schedule('schedule_checkin', $session->branch_id, $attendance->student?->phone,
            [...$vars, 'sapaan' => $attendance->student?->guardian_name ?: $attendance->student?->name]);
        self::schedule('admin_checkin', $session->branch_id, $session->branch?->user?->handphone, $vars);
    }

    /** Satu pintu notifikasi Jadwal: sakelar & nomor dicek di queue(), isi pesan dari template branch (bisa diubah owner). */
    private static function schedule(string $kind, string $branchId, ?string $phone, array $vars): bool
    {
        $branch = UserBranch::find($branchId);

        return $branch !== null && self::queue($branchId, $kind, $phone, ScheduleNotice::render($branch, $kind, $vars));
    }

    /** Kata template yang sama untuk semua pesan tentang satu sesi. */
    private static function sessionVars(ScheduleSession $session): array
    {
        return [
            'branch' => $session->branch?->name ?? config('app.name'),
            'kelas' => $session->grade?->label(),
            'hari_tanggal' => $session->start_at->translatedFormat('l, d F Y'),
            'jam' => $session->start_at->format('H.i'),
            'pengajar' => $session->teacher?->name ?? '-',
            'ruang' => $session->room?->name ?? '-',
        ];
    }

    /** Satu sesi dalam satu-dua baris, dipakai rekap & info perubahan. */
    private static function sessionLine(ScheduleSession $session): string
    {
        return '🕐 '.$session->start_at->translatedFormat('D, d M').' · '.$session->timeRange().' · Ruang '.($session->room?->name ?? '-')."\n"
            .'📚 '.$session->grade?->label()."\n"
            .'👥 '.($session->attendances->pluck('student.name')->filter()->join(', ') ?: 'Belum ada murid');
    }

    public function handle(WaGateway $wa): void
    {
        // Dicek ulang saat dikirim: sakelar bisa dimatikan / nomor terputus selama pesan menunggu di antrean.
        $device = WaDevice::connectedByBranch($this->branchId)[$this->branchId] ?? null;

        if (! $device || ! UserBranch::find($this->branchId)?->waNotifies($this->kind)) {
            return;
        }

        $result = $wa->sendText($this->branchId, ['device_id' => $device, 'to' => $this->phone, 'text' => $this->text, 'client_ref' => (string) Str::uuid()]);

        if (! $result['success']) {
            Log::warning('[WA NOTIF] Gagal kirim', ['branch' => $this->branchId, 'kind' => $this->kind, 'status' => $result['status'] ?? null, 'message' => $result['message'] ?? null]);
        }
    }
}
