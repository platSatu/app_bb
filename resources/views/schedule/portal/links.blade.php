@extends('layouts.dashboard')

@section('title', 'Portal Murid & Pengajar')
@section('page-section', 'Schedules')

@php
    $types = [
        'student' => ['title' => 'Link Daftar Murid', 'icon' => 'ri-graduation-cap-line', 'color' => 'primary', 'hint' => 'Untuk murid atau orang tua. Satu akun orang tua bisa mendaftarkan lebih dari satu anak.'],
        'teacher' => ['title' => 'Link Daftar Pengajar', 'icon' => 'ri-user-star-line', 'color' => 'info', 'hint' => 'Untuk pengajar. Pengajar yang sudah punya akun di branch lain cukup membuka link ini dan klik Ajukan.'],
    ];
@endphp

@section('content')
    @include('schedule.portal._tabs')

    <div class="alert alert-light border d-flex gap-2 mb-3">
        <i class="ri-lightbulb-line fs-5 text-warning"></i>
        <div class="small">
            Bagikan link ini lewat WhatsApp. Setiap yang mendaftar akan masuk ke tab <strong>Pendaftar</strong> dan baru bisa melihat jadwal setelah Anda setujui.
            Kalau link sempat tersebar ke orang yang salah, klik <strong>Ganti Link</strong>.
        </div>
    </div>

    @foreach ($branches as $branch)
        <h6 class="mb-2 mt-4 d-flex align-items-center gap-2">
            <i class="ri-store-2-line text-muted"></i> {{ $branch->name }}
            @if (! $branch->isActive()) <span class="badge bg-secondary-subtle text-secondary">Branch tidak aktif</span> @endif
        </h6>
        <div class="row g-3">
            @foreach ($types as $type => $meta)
                @php($link = $links->get($branch->id)?->firstWhere('type', $type))
                @continue(! $link)
                <div class="col-lg-6">
                    <div class="card mb-0 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="avatar-sm rounded-circle bg-{{ $meta['color'] }}-subtle text-{{ $meta['color'] }} d-flex align-items-center justify-content-center flex-shrink-0" style="width:42px;height:42px">
                                    <i class="{{ $meta['icon'] }} fs-4"></i>
                                </span>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="mb-0">{{ $meta['title'] }}</h6>
                                        <span class="badge {{ $link->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $link->is_active ? 'Dibuka' : 'Ditutup' }}</span>
                                    </div>
                                    <div class="small text-muted">{{ $meta['hint'] }}</div>
                                </div>
                            </div>

                            <div class="input-group mb-3" data-copy-link>
                                <input type="text" class="form-control form-control-sm bg-light {{ $link->is_active ? '' : 'text-muted' }}" value="{{ $link->url() }}" readonly>
                                <button type="button" class="btn btn-sm btn-primary" data-copy @disabled(! $link->is_active)><i class="ri-file-copy-line"></i> Salin</button>
                                <a href="https://wa.me/?text={{ rawurlencode('Silakan daftar ke portal '.$branch->name.' lewat link ini: '.$link->url()) }}" target="_blank" rel="noopener"
                                    class="btn btn-sm btn-success {{ $link->is_active ? '' : 'disabled' }}"><i class="ri-whatsapp-line"></i></a>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('schedules.portal.links.toggle', $link) }}">
                                    @csrf
                                    <button class="btn btn-sm rounded-pill {{ $link->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}">
                                        <i class="{{ $link->is_active ? 'ri-lock-line' : 'ri-lock-unlock-line' }}"></i> {{ $link->is_active ? 'Tutup Pendaftaran' : 'Buka Pendaftaran' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('schedules.portal.links.regenerate', $link) }}" onsubmit="return confirm('Ganti link? Link lama langsung tidak bisa dipakai.')">
                                    @csrf
                                    <button class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-refresh-line"></i> Ganti Link</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-copy-link]').forEach(function (group) {
            var button = group.querySelector('[data-copy]');
            var input = group.querySelector('input');
            button.addEventListener('click', function () {
                var done = function () {
                    var original = button.innerHTML;
                    button.innerHTML = '<i class="ri-check-line"></i> Tersalin';
                    setTimeout(function () { button.innerHTML = original; }, 1500);
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(input.value).then(done);
                } else {
                    input.select();
                    document.execCommand('copy');
                    done();
                }
            });
        });
    </script>
@endpush
