@extends('layouts.dashboard')

@section('title', 'Notifikasi')
@section('page-section', 'Schedules')

@use('App\Support\ScheduleNotice')

@php
    // Nama ramah untuk tiap {kata} di template, dan contoh isinya untuk pratinjau.
    $words = [
        'sapaan' => ['Nama sapaan', 'Bu Rina'], 'murid' => ['Nama murid', 'Kevin'], 'kelas' => ['Kelas', 'Piano › Pop › Grade A'],
        'hari_tanggal' => ['Hari & tanggal', 'Senin, 23 November 2026'], 'jam' => ['Jam mulai', '13.00'], 'jam_hadir' => ['Jam hadir', '12.56'],
        'pengajar' => ['Nama pengajar', 'Kak Dimas'], 'ruang' => ['Ruang', 'Studio A'], 'kartu_absen' => ['Link kartu absen', 'https://bizbos.id/kartu-absen/…'],
        'branch' => ['Nama branch', 'Bizbos Music Studio'], 'jumlah' => ['Jumlah pertemuan', '3'], 'perubahan' => ['Isi perubahan', "📅 Jadwal pertemuan dipindah.\nSebelumnya: Senin, 23 Nov 2026 pukul 13.00."],
        'daftar_sesi' => ['Daftar jadwal', "🕐 Sen, 23 Nov · 13.00–13.30 · Ruang Studio A\n📚 Piano › Pop › Grade A\n👥 Kevin\n\n🕐 Sen, 23 Nov · 14.00–14.30 · Ruang Studio A\n📚 Piano › Pop › Grade B\n👥 Sasha, Nadia"],
        'detail_sesi' => ['Detail pertemuan', "🕐 Sel, 24 Nov · 14.00–14.30 · Ruang Studio A\n📚 Piano › Pop › Grade A\n👥 Kevin"],
    ];
    $times = ['schedule' => 'schedule_at', 'teacher_recap' => 'teacher_recap_at'];
@endphp

@section('content')
    @include('schedule._tabs')

    <div class="alert alert-light border d-flex gap-3 align-items-start mb-3">
        <i class="ri-chat-smile-2-line fs-3 text-success"></i>
        <div class="small">
            <div class="fw-semibold text-body mb-1">Biar murid, orang tua, dan pengajar tidak ketinggalan kabar.</div>
            Nyalakan pesan yang Anda perlukan, lalu sesuaikan kalimatnya kalau mau. Semua pesan dikirim otomatis dari nomor WhatsApp branch
            dan memakai kuota paket WhatsApp. Kata dalam kurung kurawal, misalnya <code>{murid}</code>, akan terisi sendiri.
        </div>
    </div>

    @if ($branches->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="ri-whatsapp-line fs-1 text-success opacity-50 d-block mb-2"></i>
                <div class="fw-semibold">Notifikasi WhatsApp tersedia untuk branch dengan paket WhatsApp aktif.</div>
                <div class="small text-muted mb-3">Setelah paketnya aktif, pengaturannya muncul di sini.</div>
                <a href="{{ route('profile.packages') }}" class="btn btn-success rounded-pill"><i class="ri-shopping-bag-3-line me-1"></i>Lihat Paket</a>
            </div>
        </div>
    @endif

    @foreach ($branches as $branch)
        <form method="POST" action="{{ route('schedules.notifications.update', $branch) }}" class="card" id="branch-{{ $branch->id }}" data-notice-form>
            @csrf
            @method('PUT')

            <div class="card-header bg-transparent d-flex flex-wrap align-items-center gap-2">
                <i class="ri-store-2-line text-muted"></i>
                <h5 class="mb-0 me-auto">{{ $branch->name }}</h5>
                @if (isset($connected[$branch->id]))
                    <span class="badge rounded-pill bg-success-subtle text-success"><i class="ri-checkbox-circle-line"></i> WhatsApp terhubung</span>
                @else
                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis"><i class="ri-error-warning-line"></i> Nomor WhatsApp belum terhubung</span>
                @endif
            </div>

            <div class="card-body">
                @unless (isset($connected[$branch->id]))
                    <div class="alert alert-warning small py-2">Pesan belum bisa terkirim sebelum nomor WhatsApp branch ini terhubung. Hubungkan dulu di menu WhatsApp › Device &amp; Inbox, pengaturan di bawah tetap bisa disiapkan.</div>
                @endunless

                @foreach (ScheduleNotice::AUDIENCES as $audience => [$audienceLabel, $audienceIcon, $color])
                    <div class="d-flex align-items-center gap-2 mt-3 mb-1">
                        <span class="rounded-circle bg-{{ $color }}-subtle text-{{ $color }} d-inline-flex align-items-center justify-content-center" style="width:30px;height:30px"><i class="{{ $audienceIcon }}"></i></span>
                        <h6 class="mb-0">Untuk {{ $audienceLabel }}</h6>
                    </div>

                    @foreach (collect(ScheduleNotice::KINDS)->filter(fn ($meta) => $meta[0] === $audience) as $kind => [, $title, $hint, $default])
                        @php($template = old("templates.{$kind}", ScheduleNotice::template($branch, $kind)))
                        @php($editId = 'edit-'.$branch->id.'-'.$kind)
                        <div class="border rounded-3 p-3 mb-2" data-notice>
                            <div class="d-flex align-items-start gap-3">
                                <div class="flex-grow-1">
                                    <label for="sw-{{ $branch->id }}-{{ $kind }}" class="fw-semibold mb-0">{{ $title }}</label>
                                    <div class="small text-muted">{{ $hint }}</div>

                                    {{-- Waktu kirim, khusus pesan yang terjadwal. --}}
                                    @if (isset($times[$kind]))
                                        <div class="d-flex align-items-center gap-2 mt-2 small">
                                            <i class="ri-time-line text-muted"></i> Kirim mulai jam
                                            <input type="time" name="{{ $times[$kind] }}" class="form-control form-control-sm" style="width: 7.5rem"
                                                value="{{ old($times[$kind], ScheduleNotice::setting($branch, $times[$kind])) }}" required>
                                            <span class="text-muted">sehari sebelumnya</span>
                                        </div>
                                    @elseif ($kind === 'schedule_soon')
                                        <div class="d-flex align-items-center gap-2 mt-2 small">
                                            <i class="ri-time-line text-muted"></i> Kirim
                                            <select name="schedule_soon_minutes" class="form-select form-select-sm" style="width: 7.5rem">
                                                @foreach (ScheduleNotice::SOON_OPTIONS as $minutes => $label)
                                                    <option value="{{ $minutes }}" @selected((int) old('schedule_soon_minutes', ScheduleNotice::setting($branch, 'schedule_soon_minutes')) === $minutes)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <span class="text-muted">sebelum les dimulai</span>
                                        </div>
                                    @elseif ($kind === 'admin_checkin')
                                        <div class="small mt-2">
                                            @if ($adminPhone)
                                                <i class="ri-whatsapp-line text-success"></i> Dikirim ke nomor Anda: <strong>+{{ $adminPhone }}</strong>
                                            @else
                                                <i class="ri-error-warning-line text-warning"></i> Nomor WhatsApp Anda belum diisi di profil, jadi laporan ini belum bisa dikirim.
                                            @endif
                                        </div>
                                    @endif

                                    <a class="small d-inline-block mt-2 text-decoration-none" data-bs-toggle="collapse" href="#{{ $editId }}" role="button" aria-expanded="false">
                                        <i class="ri-edit-2-line"></i> Ubah isi pesan
                                    </a>
                                </div>
                                <div class="form-check form-switch mb-0 pt-1">
                                    <input type="checkbox" role="switch" class="form-check-input" style="width: 2.75em; height: 1.4em" id="sw-{{ $branch->id }}-{{ $kind }}"
                                        name="notify[{{ $kind }}]" value="1" @checked(old("notify.{$kind}", ! empty($branch->wa_notify[$kind])))>
                                </div>
                            </div>

                            <div class="collapse {{ $errors->has("templates.{$kind}") ? 'show' : '' }}" id="{{ $editId }}">
                                <div class="row g-3 mt-1">
                                    <div class="col-lg-7">
                                        <textarea name="templates[{{ $kind }}]" rows="9" maxlength="{{ ScheduleNotice::TEMPLATE_MAX }}" class="form-control form-control-sm @error("templates.{$kind}") is-invalid @enderror"
                                            data-template data-default="{{ $default }}">{{ $template }}</textarea>
                                        @error("templates.{$kind}") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div class="small text-muted mt-2 mb-1">Klik untuk menyisipkan:</div>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach (ScheduleNotice::VARIABLES[$kind] as $word)
                                                <button type="button" class="btn btn-sm btn-light border rounded-pill py-0" data-insert="{{ '{'.$word.'}' }}">{{ $words[$word][0] ?? $word }}</button>
                                            @endforeach
                                        </div>
                                        <button type="button" class="btn btn-sm btn-link px-0 mt-2 text-muted" data-reset><i class="ri-arrow-go-back-line"></i> Pakai pesan bawaan</button>
                                    </div>
                                    <div class="col-lg-5">
                                        <div class="small text-muted mb-1">Contoh tampilannya di WhatsApp:</div>
                                        <div class="rounded-3 p-3" style="background:#efeae2">
                                            <div class="rounded-3 p-2 px-3 small shadow-sm ms-auto" style="background:#d9fdd3; white-space:pre-line; max-width: 100%; word-break: break-word" data-preview></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>

            <div class="card-footer bg-transparent d-flex justify-content-end">
                <button type="submit" class="btn btn-success"><i class="ri-save-line me-1"></i>Simpan Pengaturan</button>
            </div>
        </form>
    @endforeach
@endsection

@push('scripts')
    <script>
        // Pratinjau pesan (kata {..} diganti contoh), sisip kata di posisi kursor, dan kembali ke pesan bawaan.
        (() => {
            const samples = @json(collect($words)->map(fn ($w) => $w[1]));
            const render = (text) => text.replace(/\{([a-z_]+)\}/g, (all, key) => samples[key] ?? all);

            document.querySelectorAll('[data-notice]').forEach((box) => {
                const area = box.querySelector('[data-template]');
                const preview = box.querySelector('[data-preview]');
                const refresh = () => { preview.textContent = render(area.value); };

                area.addEventListener('input', refresh);
                box.querySelectorAll('[data-insert]').forEach((button) => button.addEventListener('click', () => {
                    const word = button.dataset.insert;
                    const at = area.selectionStart ?? area.value.length;
                    area.setRangeText(word, at, area.selectionEnd ?? at, 'end');
                    area.focus();
                    refresh();
                }));
                box.querySelector('[data-reset]').addEventListener('click', () => { area.value = area.dataset.default; refresh(); });
                refresh();
            });
        })();
    </script>
@endpush
