<?php

namespace App\Support;

use App\Models\ScheduleRescheduleRequest;
use App\Models\ScheduleSession;
use App\Models\ScheduleStudent;

/**
 * Semua kalimat chatbot WhatsApp Jadwal di satu tempat, supaya nadanya seragam: ramah, singkat, bahasa sehari-hari.
 * Kalimat sengaja tanpa nama usaha di luar {branch}: bizbos dipakai banyak pelanggan.
 * Format WhatsApp: *tebal*, satu pilihan per baris, angka di depan.
 */
final class ScheduleBotText
{
    /** Pilihan "bicara dengan admin" yang selalu ada di setiap pertanyaan bot. */
    public const HANDOVER_KEY = '9';

    public static function sapaan(?ScheduleStudent $student): string
    {
        return trim((string) ($student?->guardian_name ?: $student?->name)) ?: 'Kak';
    }

    /** "Sabtu, 28 Nov · 10.30–11.00" */
    public static function when(ScheduleSession $session): string
    {
        return $session->start_at->translatedFormat('l, d M').' · '.$session->start_at->format('H.i').'–'.$session->end_at->format('H.i');
    }

    // ---------------------------------------------------------------- Murid / orang tua

    /** Ditempel di bawah pengingat les (template owner tetap utuh di atasnya). */
    public static function reminderFooter(ScheduleStudent $student, bool $soon): string
    {
        return "\n\n".($soon
            ? "Balas *1* kalau {$student->name} siap hadir 👍\nBalas *2* kalau ternyata berhalangan."
            : "Balas *1* untuk konfirmasi {$student->name} hadir 👍\nBalas *2* kalau berhalangan.");
    }

    public static function confirmed(ScheduleStudent $student): string
    {
        return 'Terima kasih '.self::sapaan($student).', konfirmasinya sudah kami catat 🙏 Sampai jumpa di kelas ya!';
    }

    public static function askReason(ScheduleStudent $student): string
    {
        return 'Baik '.self::sapaan($student).", tidak apa-apa 😊\n\nBoleh diceritakan singkat alasannya? Misalnya: sakit, ada acara keluarga.";
    }

    public static function reasonTooShort(): string
    {
        return 'Boleh ditulis sedikit lebih lengkap alasannya? Cukup satu kalimat singkat kok 🙏';
    }

    /** Izin tercatat; tanya mau sekalian ganti hari (hanya untuk pengingat H-1). */
    public static function askReschedule(ScheduleStudent $student, ScheduleSession $session): string
    {
        return "Terima kasih, izin {$student->name} untuk ".self::when($session)." sudah kami catat.\n\n"
            ."Mau sekalian ajukan ganti hari?\n*1*. Ya, pilih jadwal lain\n*2*. Tidak, izin saja";
    }

    public static function excusedOnly(ScheduleStudent $student): string
    {
        return "Baik, izin {$student->name} sudah kami catat. Terima kasih sudah mengabari 🙏";
    }

    /** Izin dari pengingat sebelum les: sudah terlalu mepet untuk ganti hari. */
    public static function excusedSoon(ScheduleStudent $student): string
    {
        return "Terima kasih sudah mengabari, izin {$student->name} hari ini sudah kami catat 🙏 Semoga lancar urusannya ya.";
    }

    /** @param list<array{start:string,end:string}> $slots */
    public static function slotList(string $teacher, array $slots): string
    {
        $lines = collect($slots)->values()->map(fn ($slot, $i) => '*'.($i + 1).'*. '.self::slotLabel($slot['start'], $slot['end']))->all();
        $lines[] = '*'.(count($slots) + 1).'*. Tidak ada yang cocok, sambungkan ke admin';

        return "Ini jadwal {$teacher} yang masih kosong:\n\n".implode("\n", $lines)."\n\nBalas dengan angka pilihan ya.";
    }

    public static function noSlots(): string
    {
        return "Mohon maaf, dalam 2 minggu ke depan belum ada jadwal kosong yang pas 🙏\nAdmin kami akan bantu carikan, mohon ditunggu ya.";
    }

    public static function slotTaken(): string
    {
        return 'Yah, jadwal itu barusan terisi 🙏 Ini pilihan terbaru yang masih kosong:';
    }

    public static function submitted(ScheduleRescheduleRequest $request): string
    {
        return "Pengajuan ganti hari sudah kami kirim ke admin ✅\n\n"
            .'🗓️ Jadwal lama: '.self::when($request->session)."\n"
            .'➡️ Jadwal baru: '.$request->slotLabel()."\n\n"
            .'Kami kabari lagi begitu admin menyetujui ya.';
    }

    public static function approvedToParent(ScheduleRescheduleRequest $request): string
    {
        $student = $request->student;

        return 'Kabar baik '.self::sapaan($student)." 😊 Pengajuan ganti hari {$student->name} disetujui.\n\n"
            .'📚 '.$request->session->grade?->label()."\n"
            .'🗓️ Jadwal baru: '.$request->slotLabel()."\n"
            .'👤 Pengajar: '.($request->slotTeacher?->name ?? '-')."\n\n"
            .'Sampai jumpa di kelas ya!';
    }

    public static function rejectedToParent(ScheduleRescheduleRequest $request): string
    {
        $student = $request->student;

        return 'Mohon maaf '.self::sapaan($student).", pengajuan ganti hari {$student->name} belum bisa kami setujui 🙏\n"
            .'Alasan: '.($request->reject_reason ?: '-')."\n\n"
            .'Izin untuk '.self::when($request->session).' tetap kami catat. Kalau ada pertanyaan, balas saja pesan ini ya.';
    }

    // ---------------------------------------------------------------- Admin

    public static function adminReview(ScheduleRescheduleRequest $request, int $waiting): string
    {
        return "📝 *Pengajuan ganti hari*\n\n"
            .'👦 '.$request->student?->name.' · '.$request->session->grade?->label()."\n"
            .'🗓️ Jadwal lama: '.self::when($request->session)."\n"
            .'➡️ Minta pindah: '.$request->slotLabel()."\n"
            .'💬 Alasan: '.($request->reason ?: '-')."\n"
            .($request->mode === ScheduleRescheduleRequest::MODE_MAKEUP ? "👥 Kelas grup: sesi pengganti dibuat otomatis setelah sesi lama ditutup.\n" : '')
            .($waiting > 1 ? "\n(".($waiting - 1)." pengajuan lain menunggu setelah ini)\n" : '')
            ."\nBalas *1* untuk setujui, *2* untuk tolak.";
    }

    public static function adminApproved(ScheduleRescheduleRequest $request): string
    {
        return "Siap, disetujui ✅ {$request->student?->name} sudah kami kabari jadwal barunya."
            .($request->mode === ScheduleRescheduleRequest::MODE_MAKEUP ? ' Sesi penggantinya dibuat otomatis setelah sesi lama ditutup.' : '');
    }

    public static function adminApproveFailed(string $reason): string
    {
        return "Maaf, pengajuan ini belum bisa disetujui 🙏\n{$reason}\n\nPengajuannya tetap menunggu, silakan atur lewat website di menu Schedules › Ganti Hari.";
    }

    public static function askRejectReason(): string
    {
        return 'Boleh tulis alasan singkatnya? Alasan ini akan kami sampaikan ke orang tua.';
    }

    public static function adminRejected(ScheduleRescheduleRequest $request): string
    {
        return "Baik, pengajuan {$request->student?->name} ditolak dan alasannya sudah kami sampaikan ke orang tua.";
    }

    public static function adminAlreadyProcessed(): string
    {
        return 'Pengajuan ini sudah diproses sebelumnya (mungkin lewat website) 👍';
    }

    public static function adminNothingWaiting(): string
    {
        return 'Tidak ada pengajuan ganti hari yang menunggu saat ini 👍';
    }

    public static function adminMakeupFailed(ScheduleRescheduleRequest $request, string $reason): string
    {
        return "⚠️ Sesi pengganti {$request->student?->name} (".$request->slotLabel().") belum bisa dibuat.\n{$reason}\n\n"
            .'Silakan atur lewat website di menu Schedules › Ganti Hari.';
    }

    // ---------------------------------------------------------------- Perubahan jadwal ke orang tua

    public static function movedToParent(ScheduleStudent $student, ScheduleSession $session, \Carbon\CarbonInterface $was): string
    {
        return 'Halo '.self::sapaan($student).", ada perubahan jadwal les {$student->name} 🔔\n\n"
            .'📚 '.$session->grade?->label()."\n"
            .'🗓️ Jadwal baru: '.self::when($session)."\n"
            .'👤 Pengajar: '.($session->teacher?->name ?? '-')."\n"
            .'(Sebelumnya: '.$was->translatedFormat('l, d M').' pukul '.$was->format('H.i').")\n\n"
            .'Mohon dicek ya. Terima kasih!';
    }

    public static function cancelledToParent(ScheduleStudent $student, ScheduleSession $session, string $reason): string
    {
        return 'Halo '.self::sapaan($student).", les {$student->name} pada ".self::when($session)." dibatalkan 🙏\n"
            ."Alasan: {$reason}\n\n"
            .'Kalau ada jadwal penggantinya, kami kabari lagi ya.';
    }

    // ---------------------------------------------------------------- Pengajar

    public static function recapFooter(): string
    {
        return "\n\nBalas *1* kalau siap mengajar 👍\nBalas *2* kalau ada jadwal yang berhalangan.";
    }

    public static function recapThanks(string $teacher): string
    {
        return "Terima kasih Kak {$teacher}, semangat mengajar besok! 💪";
    }

    /** @param \Illuminate\Support\Collection<int, ScheduleSession> $sessions */
    public static function recapPick(\Illuminate\Support\Collection $sessions): string
    {
        $lines = $sessions->values()->map(fn (ScheduleSession $s, $i) => '*'.($i + 1).'*. '.self::when($s).' · '.$s->grade?->label())->all();
        $lines[] = '*'.($sessions->count() + 1).'*. Semua jadwal besok';

        return "Jadwal mana yang berhalangan, Kak?\n\n".implode("\n", $lines)."\n\nBalas nomornya, boleh lebih dari satu (contoh: 1,3).";
    }

    public static function recapReported(): string
    {
        return "Terima kasih sudah mengabari, Kak 🙏 Admin sudah kami beri tahu dan akan mengatur jadwalnya.\nKabar perubahannya nanti dikirim lewat WhatsApp ya.";
    }

    /** @param \Illuminate\Support\Collection<int, ScheduleSession> $sessions */
    public static function adminTeacherUnavailable(string $teacher, \Illuminate\Support\Collection $sessions): string
    {
        return "⚠️ *Pengajar berhalangan*\n\n👤 {$teacher} mengabarkan tidak bisa mengajar:\n"
            .$sessions->map(fn (ScheduleSession $s) => '• '.self::when($s).' · '.$s->grade?->label().' ('.$s->attendances->pluck('student.name')->filter()->join(', ').')')->join("\n")
            ."\n\nSilakan atur (pindah jadwal / ganti pengajar) lewat website, menu Schedules › Sesi & Absensi. Orang tua otomatis dikabari kalau jadwalnya dipindah.";
    }

    /** @param list<string> $names murid yang belum diabsen */
    public static function attendPrompt(string $teacher, ScheduleSession $session, array $names): string
    {
        return "Halo Kak {$teacher} 👋 Sesi ".$session->grade?->label().' pukul '.$session->start_at->format('H.i')." sudah dimulai.\n\n"
            ."Yang belum diabsen:\n".collect($names)->map(fn ($n) => '• '.$n)->join("\n")."\n\n"
            ."Balas *1* kalau semua hadir ✅\nBalas *2* kalau ada yang tidak hadir.";
    }

    /** @param list<string> $names */
    public static function attendPick(array $names): string
    {
        return "Siapa yang tidak hadir, Kak?\n\n".collect($names)->values()->map(fn ($n, $i) => '*'.($i + 1).'*. '.$n)->join("\n")
            ."\n\nBalas nomornya, boleh lebih dari satu (contoh: 1,3).";
    }

    /** @param list<string> $names */
    public static function attendReason(array $names): string
    {
        return 'Alasan '.collect($names)->join(', ', ' dan ')." tidak hadir?\n*1*. Sakit / izin\n*2*. Tanpa keterangan";
    }

    /** @param list<string> $present @param list<string> $absent */
    public static function attendConfirm(array $present, array $absent, bool $excused): string
    {
        return "Mohon dicek dulu ya, Kak:\n\n"
            .($present ? '✅ Hadir: '.implode(', ', $present)."\n" : '')
            .($excused ? '🟡 Izin: ' : '🔴 Tanpa keterangan: ').implode(', ', $absent)."\n\n"
            .'Balas *1* untuk simpan, *2* untuk ulangi.';
    }

    public static function attendSaved(array $counts, bool $closed): string
    {
        return "Terima kasih Kak, absen sudah tersimpan ✅\n"
            .'Hadir '.($counts['present'] ?? 0).' · Izin '.($counts['excused'] ?? 0).' · Tanpa keterangan '.($counts['absent'] ?? 0)."\n"
            .($closed ? 'Sesi sudah ditutup. Selamat mengajar! 🙌' : 'Sesi akan ditutup otomatis.');
    }

    public static function attendAllDone(): string
    {
        return 'Semua murid di sesi ini sudah tercatat, tidak perlu diabsen lagi 👍';
    }

    public static function onlineStartPrompt(string $teacher, ScheduleSession $session): string
    {
        return "Halo Kak {$teacher}, sesi online ".$session->grade?->label().' pukul '.$session->start_at->format('H.i')." sebentar lagi dimulai 💻\n\n"
            .'Balas *1* saat Kakak mulai mengajar, supaya kehadiran Kakak tercatat.';
    }

    public static function onlineStarted(): string
    {
        return 'Siap, kehadiran Kakak tercatat pukul '.now()->format('H.i').' ✅ Selamat mengajar!';
    }

    public static function onlineTooEarly(int $minutes): string
    {
        return "Belum bisa ya Kak, absen mulai bisa {$minutes} menit sebelum sesi dimulai 🙏";
    }

    public static function teacherOptions(): string
    {
        return "Balas *1* atau *2* ya, Kak.\nAtau *9* untuk bicara dengan admin.";
    }

    // ---------------------------------------------------------------- Menu admin

    public const MENU_KEYWORDS = ['menu', 'admin', 'menu admin'];

    public static function adminMenu(string $owner, string $branch, int $waiting): string
    {
        return "👋 Halo {$owner}, ini menu admin *{$branch}*:\n\n"
            ."*1*. Rekap absensi\n"
            .'*2*. Pengajuan ganti hari'.($waiting ? " ({$waiting} menunggu)" : '')."\n"
            ."*3*. Link pendaftaran & pendaftar\n"
            ."*4*. Tagihan les\n"
            ."*5*. Kirim pengingat les besok ke semua murid\n"
            ."*6*. Kirim rekap mengajar besok ke semua pengajar\n\n"
            .'Balas angkanya. Ketik *0* kapan saja untuk kembali ke menu.';
    }

    public static function backHint(): string
    {
        return "\n\nBalas *0* untuk kembali ke menu.";
    }

    public static function rekapPeriod(): string
    {
        return "Rekap absensi untuk kapan?\n\n*1*. Hari ini\n*2*. Minggu ini\n*3*. Bulan ini\n*4*. Pilih tanggal";
    }

    public static function askDate(): string
    {
        return 'Tulis tanggalnya ya, contoh: *28/11* atau *28/11/2026*.';
    }

    /**
     * @param array{done:int, scheduled:int, cancelled:int} $sessions
     * @param array<string, int> $attendance status => jumlah
     * @param list<string> $lines rincian per sesi (khusus rekap satu hari)
     */
    public static function rekap(string $label, array $sessions, array $attendance, int $late, array $lines, string $url): string
    {
        $total = array_sum($sessions);

        if ($total === 0) {
            return "📊 Rekap absensi {$label}\n\nBelum ada sesi les di waktu ini.".self::backHint();
        }

        return "📊 *Rekap absensi {$label}*\n\n"
            ."🗓️ Sesi: {$total} (selesai {$sessions['done']} · terjadwal {$sessions['scheduled']} · batal {$sessions['cancelled']})\n"
            .'✅ Hadir '.($attendance['present'] ?? 0).' · 🟡 Izin '.($attendance['excused'] ?? 0).' · 🔴 Alpa '.($attendance['absent'] ?? 0)
            .' · ⏳ Belum diisi '.($attendance['pending'] ?? 0)."\n"
            .($late ? "\n⚠️ {$late} sesi sudah lewat tapi belum diabsen.\n" : '')
            .($lines ? "\n".implode("\n", $lines)."\n" : '')
            ."\nDetail lengkap: {$url}".self::backHint();
    }

    public static function requestsMenu(int $waiting): string
    {
        return "Ada *{$waiting}* pengajuan ganti hari yang menunggu.\n\n*1*. Setujui semua (pakai PIN)\n*2*. Lihat satu per satu";
    }

    /** @param list<string> $ok @param list<string> $failed */
    public static function approvedAll(array $ok, array $failed): string
    {
        return '✅ '.count($ok).' pengajuan disetujui, orang tua sudah dikabari.'
            .($failed ? "\n\n⚠️ Belum bisa disetujui:\n".implode("\n", $failed)."\n\nAtur sisanya lewat website, menu Schedules › Ganti Hari." : '')
            .self::backHint();
    }

    public static function signupMenu(): string
    {
        return "*1*. Kirim link pendaftaran\n*2*. Lihat yang mendaftar";
    }

    /** @param array<string, array{label:string,url:string,active:bool}> $links */
    public static function signupLinks(string $branch, array $links): string
    {
        return "🔗 *Link pendaftaran {$branch}*\nTeruskan pesan ini ke calon murid / pengajar ya.\n\n"
            .collect($links)->map(fn ($l) => "{$l['label']}:\n{$l['url']}".($l['active'] ? '' : "\n(sedang DITUTUP, buka lagi di website)"))->join("\n\n")
            .self::backHint();
    }

    public static function signupPeriod(): string
    {
        return "Pendaftar sejak kapan?\n\n*1*. Hari ini\n*2*. Minggu ini\n*3*. Bulan ini";
    }

    /** @param array<string, int> $counts status => jumlah @param list<string> $lines */
    public static function signups(string $label, array $counts, array $lines, string $url): string
    {
        if (array_sum($counts) === 0) {
            return "Belum ada yang mendaftar {$label}.".self::backHint();
        }

        return "📝 *Pendaftar {$label}*\n\n"
            .'⏳ Menunggu '.($counts['pending'] ?? 0).' · ✅ Disetujui '.($counts['approved'] ?? 0).' · ✖️ Ditolak '.($counts['rejected'] ?? 0)."\n\n"
            .implode("\n", $lines)
            ."\n\nSetujui pendaftar lewat website: {$url}".self::backHint();
    }

    public static function billMenu(string $month): string
    {
        return "Tagihan les {$month}:\n\n*1*. Buat tagihan\n*2*. Cek status tagihan";
    }

    public static function billWho(int $students): string
    {
        return "Buat tagihan untuk siapa?\n\n*1*. Semua murid aktif ({$students} murid, pakai PIN)\n*2*. Cari nama murid";
    }

    public static function askStudentName(): string
    {
        return 'Tulis nama murid yang mau ditagih (minimal 3 huruf).';
    }

    /** @param list<string> $names */
    public static function pickStudent(array $names): string
    {
        return "Murid yang cocok:\n\n".collect($names)->values()->map(fn ($n, $i) => '*'.($i + 1).'*. '.$n)->join("\n")."\n\nBalas nomornya ya.";
    }

    public static function confirmBill(string $name, string $month): string
    {
        return "Buat tagihan les {$month} untuk *{$name}*?\nTagihan langsung dikirim ke orang tua.\n\n*1*. Ya, buat\n*2*. Batal";
    }

    public static function billQueued(int $students, string $month): string
    {
        return "Siap, tagihan {$month} untuk {$students} murid sedang dibuat ⏳\nHasilnya kami kirim ke sini begitu selesai.".self::backHint();
    }

    /** @param array{created:int, notes:list<string>} $result */
    public static function billResult(array $result, \Carbon\CarbonInterface $month): string
    {
        return ($result['created'] ? "✅ {$result['created']} tagihan ".$month->translatedFormat('F Y').' dibuat dan dikirim ke orang tua.' : 'Belum ada tagihan baru yang dibuat.')
            .($result['notes'] ? "\n\nCatatan:\n".collect($result['notes'])->take(8)->map(fn ($n) => '• '.$n)->join("\n").(count($result['notes']) > 8 ? "\n…" : '') : '');
    }

    /** @param array<string, int> $counts @param list<string> $unpaid */
    public static function billStatus(string $month, array $counts, int $unpaidTotal, array $unpaid, string $url): string
    {
        if (array_sum($counts) === 0) {
            return "Belum ada tagihan les {$month}.".self::backHint();
        }

        return "💳 *Tagihan les {$month}*\n\n"
            .'✅ Lunas '.($counts['paid'] ?? 0).' · ⏳ Belum bayar '.($counts['unpaid'] ?? 0).' · ⌛ Kedaluwarsa '.($counts['expired'] ?? 0)."\n"
            .($unpaidTotal ? 'Total belum dibayar: Rp '.number_format($unpaidTotal, 0, ',', '.')."\n" : '')
            .($unpaid ? "\nBelum bayar:\n".implode("\n", $unpaid)."\n" : '')
            ."\nDetail: {$url}".self::backHint();
    }

    public static function confirmBlast(string $who, int $count, bool $switchOn): string
    {
        if (! $switchOn) {
            return "Sakelar pesan untuk {$who} masih mati di Schedules › Notifikasi, jadi belum bisa dikirim. Nyalakan dulu ya.".self::backHint();
        }

        if ($count === 0) {
            return "Tidak ada {$who} yang perlu dikirimi untuk jadwal besok (atau semuanya sudah dikirimi hari ini) 👍".self::backHint();
        }

        return "Pesan untuk jadwal besok akan dikirim ke *{$count} {$who}*.\nYang sudah dikirimi tidak akan dikirim dua kali.\n\n*1*. Kirim sekarang (pakai PIN)\n*2*. Batal";
    }

    public static function blastSent(string $who, int $count): string
    {
        return "✅ {$count} pesan untuk {$who} sedang dikirim.".self::backHint();
    }

    public static function askPin(): string
    {
        return "🔒 Masukkan PIN admin Anda (4–6 angka).\nSetelah itu, hapus pesan PIN dari chat ini ya.";
    }

    public static function noPin(): string
    {
        return "🔒 Aksi ini butuh PIN admin, tapi PIN Anda belum dibuat.\nBuat dulu di website: menu Schedules › Notifikasi › PIN Admin WhatsApp.".self::backHint();
    }

    public static function wrongPin(int $left): string
    {
        return "PIN salah 🙏 Sisa {$left} kali percobaan.";
    }

    public static function pinLocked(int $minutes): string
    {
        return "Terlalu banyak PIN salah. Demi keamanan, coba lagi {$minutes} menit lagi ya.".self::backHint();
    }

    public static function cancelled(): string
    {
        return 'Baik, dibatalkan.'.self::backHint();
    }

    // ---------------------------------------------------------------- Umum

    public static function invalid(string $options): string
    {
        return "Maaf, balasannya belum kami pahami 🙏\n\n{$options}";
    }

    public static function confirmOptions(): string
    {
        return "Balas *1* untuk konfirmasi hadir, *2* kalau berhalangan.\nAtau *9* untuk bicara dengan admin.";
    }

    public static function yesNoOptions(): string
    {
        return "Balas *1* atau *2* ya.\nAtau *9* untuk bicara dengan admin.";
    }

    public static function handover(): string
    {
        return 'Baik, admin kami akan segera membalas ya 🙏';
    }

    public static function expired(): string
    {
        return 'Maaf, pesan pengingat tadi sudah tidak berlaku (jadwalnya mungkin sudah lewat atau berubah) 🙏 Kalau ada yang ingin ditanyakan, silakan tulis saja, admin kami akan membantu.';
    }

    private static function slotLabel(string $start, string $end): string
    {
        $from = \Illuminate\Support\Carbon::parse($start);

        return $from->translatedFormat('l, d M').' · '.$from->format('H.i').'–'.\Illuminate\Support\Carbon::parse($end)->format('H.i');
    }
}
