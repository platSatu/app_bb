@extends('layouts.dashboard')

@section('title', 'Flow Jadwal')
@section('page-section', 'WhatsApp')

@php
    /*
     * Alur Flow Jadwal bawaan, hanya untuk dibaca. Isinya mengikuti App\Services\WhatsApp\Schedule\{ScheduleBot, TeacherBot, AdminMenu}
     * dan kalimat di App\Support\ScheduleBotText. Setiap langkah: [kapan / pemicu, contoh pesan bot, [pilihan => hasil]].
     */
    $flows = [
        'parent' => ['Orang Tua / Murid', 'ri-parent-line', 'primary', [
            ['Pengingat les H-1 (jam sesuai pengaturan)', "Halo Mom Natan, pengingat les Natan besok ya 😊\n📚 Piano › Pop › Grade A\n🗓️ Sabtu, 28 November 2026, pukul 10.30\n\nBalas *1* untuk konfirmasi Natan hadir 👍\nBalas *2* kalau berhalangan.", [
                '1 · Konfirmasi' => 'Tercatat "konfirmasi hadir" di absensi, bot mengucapkan terima kasih.',
                '2 · Berhalangan' => 'Bot menanyakan alasan → tercatat Izin + alasan (tidak ditagih).',
            ]],
            ['Setelah alasan (khusus pengingat H-1)', "Mau sekalian ajukan ganti hari?\n*1*. Ya, pilih jadwal lain\n*2*. Tidak, izin saja", [
                '1 · Pilih jadwal lain' => 'Bot menampilkan maks. 5 jadwal kosong pengajar yang sama (14 hari ke depan) + "tidak ada yang cocok → admin".',
                '2 · Izin saja' => 'Selesai, izin tetap tercatat.',
            ]],
            ['Pilih jadwal kosong', "Ini jadwal Bu Sari yang masih kosong:\n*1*. Senin, 30 Nov · 15.00–15.30\n*2*. …", [
                'Nomor jadwal' => 'Pengajuan ganti hari dibuat (menunggu admin), admin dikirimi WhatsApp.',
                'Disetujui admin' => 'Privat: sesi dipindah. Grup: sesi pengganti dibuat otomatis setelah sesi lama ditutup. Orang tua dikabari.',
                'Ditolak admin' => 'Orang tua menerima alasan penolakan, izin tetap tercatat.',
            ]],
            ['Pengingat sebelum les (hari H)', "Halo Mom Natan, les Natan sebentar lagi dimulai ya ⏰\n\nBalas *1* kalau Natan siap hadir 👍\nBalas *2* kalau ternyata berhalangan.", [
                '1 / 2' => 'Sama seperti di atas, tapi tanpa ganti hari (sudah terlalu mepet).',
            ]],
        ]],
        'teacher' => ['Pengajar', 'ri-user-star-line', 'info', [
            ['Rekap mengajar H-1', "Halo Bu Sari, ini jadwal mengajar Anda besok…\n\nBalas *1* kalau siap mengajar 👍\nBalas *2* kalau ada jadwal yang berhalangan.", [
                '1 · Siap' => 'Bot mengucapkan terima kasih.',
                '2 · Berhalangan' => 'Pilih jadwal yang berhalangan → tercatat di sesi & admin dikabari (admin yang mengatur).',
            ]],
            ['Setelah scan QR tempat (cek GPS) & sesi dimulai', "Halo Kak Bu Sari 👋 Sesi Piano › Pop › Grade A pukul 10.30 sudah dimulai.\n\nYang belum diabsen:\n• Natan\n• Astrid\n\nBalas *1* kalau semua hadir ✅\nBalas *2* kalau ada yang tidak hadir.", [
                '1 · Semua hadir' => 'Semua murid "belum diisi" jadi Hadir, orang tua & admin dikabari, sesi ditutup otomatis (honor dihitung).',
                '2 · Ada yang tidak hadir' => 'Pilih murid (contoh 1,3) → pilih alasan (1 sakit/izin, 2 tanpa keterangan) → konfirmasi → tersimpan.',
            ]],
            ['Kelas online (ruang ditandai online), 10 menit sebelum', "Halo Kak Bu Sari, sesi online Piano pukul 10.30 sebentar lagi dimulai 💻\n\nBalas *1* saat Kakak mulai mengajar, supaya kehadiran Kakak tercatat.", [
                '1 · Mulai' => 'Absen datang pengajar tercatat (via WhatsApp). Saat sesi mulai, daftar absen murid dikirim seperti di atas.',
            ]],
        ]],
        'admin' => ['Admin (nomor owner)', 'ri-shield-user-line', 'warning', [
            ['Pengajuan ganti hari masuk (otomatis)', "📝 Pengajuan ganti hari\n👦 Natan · Piano › Pop › Grade A\n🗓️ Jadwal lama: Sabtu, 28 Nov · 10.30–11.00\n➡️ Minta pindah: Senin, 30 Nov · 15.00–15.30\n💬 Alasan: Ada acara keluarga\n\nBalas *1* untuk setujui, *2* untuk tolak.", [
                '1 · Setujui' => 'Dicek bentrok ulang lalu diproses, orang tua dikabari. Pengajuan berikutnya langsung ditampilkan.',
                '2 · Tolak' => 'Admin menulis alasan → disampaikan ke orang tua.',
            ]],
            ['Ketik "menu"', "👋 Halo, ini menu admin:\n*1*. Rekap absensi\n*2*. Pengajuan ganti hari\n*3*. Link pendaftaran & pendaftar\n*4*. Tagihan les\n*5*. Kirim pengingat les besok ke semua murid\n*6*. Kirim rekap mengajar besok ke semua pengajar", [
                '1 · Rekap absensi' => 'Hari ini / minggu ini / bulan ini / pilih tanggal → ringkasan + link detail.',
                '2 · Pengajuan' => 'Setujui semua (PIN) atau satu per satu.',
                '3 · Pendaftaran' => 'Link daftar murid & pengajar untuk diteruskan, dan daftar pendaftar (hanya dilihat).',
                '4 · Tagihan' => 'Buat tagihan bulan ini (semua murid pakai PIN, atau cari satu murid) dan cek status lunas.',
                '5 / 6 · Kirim pengingat' => 'Konfirmasi jumlah penerima → PIN → dikirim (yang sudah dapat tidak dikirim dua kali).',
            ]],
        ]],
    ];
@endphp

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="{{ route('whatsapp.flows.index', ['branch' => $branch->id]) }}" class="btn btn-sm btn-light"><i class="ri-arrow-left-line"></i> Chatbot Flow</a>
        <h5 class="mb-0 me-auto">Flow Jadwal <span class="badge bg-light text-dark border ms-1">Bawaan</span>
            <span class="badge ms-1 {{ $on ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $on ? 'Aktif' : 'Nonaktif' }}</span>
        </h5>
        @if ($settingsUrl)
            <a href="{{ $settingsUrl }}" class="btn btn-sm btn-success rounded-pill"><i class="ri-settings-3-line me-1"></i>{{ $on ? 'Atur' : 'Nyalakan' }} di Schedules › Notifikasi</a>
        @endif
    </div>

    <div class="alert alert-light border small mb-3">
        <i class="ri-information-line"></i>
        Flow ini berjalan otomatis untuk branch <strong>{{ $branch->name }}</strong>: tidak perlu kata kunci, karena bot hanya menjawab nomor yang baru saja dikirimi pengingat / absen,
        atau nomor owner yang mengetik <em>menu</em>. Chat lain tetap ke flow, auto reply, dan AI seperti biasa. Di setiap pertanyaan, <strong>9</strong> = bicara dengan admin.
        Semua jawaban tercatat sama seperti di website.
        @unless ($hasSchedules)
            <div class="text-warning-emphasis mt-1"><i class="ri-error-warning-line"></i> Paket Jadwal branch ini belum aktif, jadi flow ini belum berjalan.</div>
        @endunless
    </div>

    <div class="row g-3">
        @foreach ($flows as $key => [$title, $icon, $color, $steps])
            <div class="col-lg-4">
                <div class="card h-100 mb-0">
                    <div class="card-header bg-transparent d-flex align-items-center gap-2">
                        <span class="rounded-circle bg-{{ $color }}-subtle text-{{ $color }} d-inline-flex align-items-center justify-content-center" style="width:30px;height:30px"><i class="{{ $icon }}"></i></span>
                        <h6 class="mb-0">{{ $title }}</h6>
                    </div>
                    <div class="card-body">
                        @foreach ($steps as $i => [$when, $message, $options])
                            <div class="position-relative ps-4 {{ $loop->last ? '' : 'pb-4' }}">
                                @unless ($loop->last)
                                    <span class="position-absolute border-start border-2" style="left: 9px; top: 22px; bottom: 0"></span>
                                @endunless
                                <span class="position-absolute rounded-circle bg-{{ $color }} text-white small d-inline-flex align-items-center justify-content-center" style="left:0; top:0; width:20px; height:20px">{{ $i + 1 }}</span>
                                <div class="fw-semibold small mb-2">{{ $when }}</div>
                                <div class="rounded-3 p-2" style="background:#efeae2">
                                    <div class="rounded-3 p-2 px-3 small shadow-sm" style="background:#fff; white-space:pre-line; word-break: break-word">{{ $message }}</div>
                                </div>
                                <ul class="list-unstyled small mt-2 mb-0">
                                    @foreach ($options as $reply => $result)
                                        <li class="d-flex gap-2 mt-1">
                                            <span class="badge rounded-pill bg-light text-dark border align-self-start text-nowrap">{{ $reply }}</span>
                                            <span class="text-muted">{{ $result }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
