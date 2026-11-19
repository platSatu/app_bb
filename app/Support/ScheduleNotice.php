<?php

namespace App\Support;

use App\Models\UserBranch;

/**
 * Notifikasi WhatsApp aplikasi Jadwal: daftar jenis, template bawaan, dan pengisian template.
 * Semua disimpan di user_branches.wa_notify (tanpa tabel baru):
 *   {"schedule": true, ..., "schedule_at": "16:00", "templates": {"schedule": "Halo {sapaan}, ..."}}
 * Template kosong / sama dengan bawaan = pakai bawaan, jadi perbaikan kalimat bawaan ikut terpakai.
 * Diatur owner di Schedules › Notifikasi.
 */
class ScheduleNotice
{
    public const AUDIENCES = [
        'student' => ['Murid & Orang Tua', 'ri-graduation-cap-line', 'primary'],
        'teacher' => ['Pengajar', 'ri-user-star-line', 'info'],
        'admin' => ['Admin', 'ri-shield-user-line', 'warning'],
    ];

    /** kind => [penerima, judul, kapan dikirim, template bawaan]. Kunci kind dipakai juga sebagai sakelar di wa_notify. */
    public const KINDS = [
        'schedule' => ['student', 'Pengingat les H-1', 'Dikirim sehari sebelum les, pada jam yang Anda pilih.',
            "Halo {sapaan}, pengingat les {murid} besok ya 😊\n\n📚 {kelas}\n🗓️ {hari_tanggal}, pukul {jam}\n👤 Pengajar: {pengajar} · Ruang {ruang}\n\nKartu absen QR (tunjukkan ke pengajar saat les dimulai):\n{kartu_absen}\n\nKalau berhalangan, kabari kami ya. Sampai jumpa!\n\n{branch}"],
        'schedule_soon' => ['student', 'Pengingat sebelum les dimulai', 'Dikirim beberapa saat sebelum les dimulai di hari yang sama.',
            "Halo {sapaan}, les {murid} sebentar lagi dimulai ya ⏰\n\n📚 {kelas}\n🕐 Pukul {jam} · Ruang {ruang}\n👤 Pengajar: {pengajar}\n\nJangan lupa bawa kartu absennya. Sampai ketemu!\n\n{branch}"],
        'schedule_checkin' => ['student', 'Kabar murid sudah hadir', 'Dikirim saat kartu absen murid discan pengajar.',
            "Halo {sapaan}, {murid} sudah hadir di les hari ini ✅\n\n📚 {kelas}\n🕐 Tercatat pukul {jam_hadir}\n👤 Pengajar: {pengajar}\n\nTerima kasih sudah mempercayakan belajarnya kepada kami.\n\n{branch}"],
        'teacher_recap' => ['teacher', 'Rekap mengajar besok', 'Dikirim sehari sebelumnya, berisi semua jadwal mengajar besok beserta muridnya.',
            "Halo {pengajar}, ini jadwal mengajar Anda besok, {hari_tanggal} 📋\n\n{daftar_sesi}\n\nTotal {jumlah} pertemuan. Semangat mengajar!\n\n{branch}"],
        'teacher_change' => ['teacher', 'Info perubahan jadwal', 'Dikirim langsung saat jadwal dipindah, dibatalkan, ganti pengajar, ada sesi pengganti, atau murid masuk/keluar. Hanya untuk pertemuan 7 hari ke depan.',
            "Halo {pengajar}, ada perubahan jadwal mengajar 🔔\n\n{perubahan}\n\n{detail_sesi}\n\nMohon dicek ya. Terima kasih!\n\n{branch}"],
        'admin_checkin' => ['admin', 'Laporan absen masuk', 'Dikirim ke nomor WhatsApp Anda setiap ada murid yang discan hadir.',
            "Absen masuk ✅\n{murid} hadir pukul {jam_hadir}\n📚 {kelas}\n👤 {pengajar} · Ruang {ruang}\n\n{branch}"],
    ];

    /** Kata yang bisa dipakai di template per jenis (ditampilkan sebagai tombol sisip di form). */
    public const VARIABLES = [
        'schedule' => ['sapaan', 'murid', 'kelas', 'hari_tanggal', 'jam', 'pengajar', 'ruang', 'kartu_absen', 'branch'],
        'schedule_soon' => ['sapaan', 'murid', 'kelas', 'hari_tanggal', 'jam', 'pengajar', 'ruang', 'kartu_absen', 'branch'],
        'schedule_checkin' => ['sapaan', 'murid', 'kelas', 'jam_hadir', 'pengajar', 'branch'],
        'teacher_recap' => ['pengajar', 'hari_tanggal', 'daftar_sesi', 'jumlah', 'branch'],
        'teacher_change' => ['pengajar', 'perubahan', 'detail_sesi', 'branch'],
        'admin_checkin' => ['murid', 'jam_hadir', 'kelas', 'pengajar', 'ruang', 'branch'],
    ];

    /** Jam & jarak waktu bawaan; owner bisa mengubahnya. */
    public const TIMES = ['schedule_at' => '16:00', 'schedule_soon_minutes' => 60, 'teacher_recap_at' => '18:00'];

    public const SOON_OPTIONS = [15 => '15 menit', 30 => '30 menit', 60 => '1 jam', 90 => '1,5 jam', 120 => '2 jam', 180 => '3 jam'];

    /** Info perubahan ke pengajar hanya untuk pertemuan sampai sekian hari ke depan. */
    public const CHANGE_DAYS = 7;

    public const TEMPLATE_MAX = 1000;

    public static function setting(UserBranch $branch, string $key): string|int
    {
        return $branch->wa_notify[$key] ?? self::TIMES[$key];
    }

    public static function template(UserBranch $branch, string $kind): string
    {
        return filled($branch->wa_notify['templates'][$kind] ?? null) ? $branch->wa_notify['templates'][$kind] : self::KINDS[$kind][3];
    }

    /** Isi {kata} di template branch. Kata yang tidak dikenal dibiarkan apa adanya supaya salah ketik terlihat. */
    public static function render(UserBranch $branch, string $kind, array $vars): string
    {
        $pairs = collect($vars)->mapWithKeys(fn ($value, $key) => ['{'.$key.'}' => (string) $value])->all();

        return trim(strtr(self::template($branch, $kind), $pairs));
    }
}
