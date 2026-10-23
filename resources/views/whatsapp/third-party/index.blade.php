@extends('layouts.dashboard')

@section('title', 'Third Party')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <p class="text-muted small mb-0">Hubungkan WhatsApp bizbos dengan website, aplikasi, atau Google Form Anda. Semua yang terhubung terlihat di sini.</p>
        @include('whatsapp._branch-filter')
    </div>

    {{-- Pintasan dokumentasi --}}
    <div class="card mb-3 border-primary-subtle">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2.75rem; height: 2.75rem;"><i class="ri-book-open-line fs-4"></i></div>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold">Dokumentasi API WhatsApp</div>
                <div class="small text-muted">Bagikan link ini ke developer Anda. Isinya cara kirim pesan, contoh kode, dan arti setiap jawaban.</div>
            </div>
            <div class="input-group input-group-sm" style="max-width: 420px;">
                <input type="text" class="form-control font-monospace" value="{{ route('docs.whatsapp-api') }}" readonly>
                <button class="btn btn-outline-secondary" type="button" data-copy="{{ route('docs.whatsapp-api') }}" title="Salin link"><i class="ri-file-copy-line"></i></button>
                <a href="{{ route('whatsapp.api-docs') }}" class="btn btn-primary">Buka</a>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- API Key per nomor --}}
        <div class="col-xl-6">
            <div class="card h-100 mb-0">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-1 fw-semibold"><i class="ri-key-2-line me-1 text-primary"></i>API Key per Nomor</h6>
                    <div class="small text-muted lh-sm">Untuk website/aplikasi yang ingin mengirim WhatsApp sendiri, mis. notifikasi pesanan atau OTP.</div>
                </div>
                <div class="card-body px-4">
                    @forelse ($devices as $device)
                        @php($key = $device->apiKey)
                        <div class="d-flex align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">{{ $device->name }}</div>
                                <div class="small text-muted">
                                    {{ $device->phone ? '+'.$device->phone : 'Nomor belum terhubung' }}
                                    @if ($key) · dipakai {{ $key->last_used_at?->diffForHumans() ?? 'belum pernah' }} @endif
                                </div>
                            </div>
                            @if (! $key)
                                <span class="badge bg-light text-muted border">Belum ada</span>
                                <a href="{{ route('whatsapp.api-key.show', $device) }}" class="btn btn-sm rounded-pill btn-outline-primary text-nowrap"><i class="ri-add-line"></i> Buat API Key</a>
                            @else
                                <span class="badge {{ $key->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $key->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                <a href="{{ route('whatsapp.api-key.show', $device) }}" class="btn btn-sm rounded-pill btn-outline-secondary text-nowrap"><i class="ri-settings-3-line"></i> Kelola</a>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">
                            <i class="ri-smartphone-line fs-2 d-block mb-1"></i>
                            Belum ada nomor WhatsApp di branch ini. <a href="{{ route('whatsapp.devices.index', ['branch' => $branch->id]) }}">Hubungkan nomor dulu</a>.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Google Form --}}
        <div class="col-xl-6">
            <div class="card h-100 mb-0">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <h6 class="mb-1 fw-semibold"><i class="ri-file-list-3-line me-1 text-success"></i>Google Form</h6>
                        <div class="small text-muted lh-sm">Balas otomatis setiap orang yang mengisi form, tanpa coding.</div>
                    </div>
                    @if ($devices->isNotEmpty())
                        <a href="{{ route('whatsapp.third-party.forms.create', ['branch' => $branch->id]) }}" class="btn btn-sm btn-primary text-nowrap"><i class="ri-link"></i> Hubungkan</a>
                    @endif
                </div>
                <div class="card-body px-4">
                    @forelse ($forms as $form)
                        <div class="d-flex align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div class="flex-grow-1 min-w-0">
                                <a href="{{ route('whatsapp.third-party.forms.show', $form) }}" class="fw-semibold text-body text-truncate d-block">{{ $form->name }}</a>
                                <div class="small text-muted">
                                    via {{ $form->device?->name ?? '-' }} · {{ number_format($form->sent_count) }} terkirim
                                    @if ($form->failed_count) · <span class="text-danger">{{ number_format($form->failed_count) }} gagal</span> @endif
                                    · terakhir {{ $form->last_triggered_at?->diffForHumans() ?? 'belum ada' }}
                                </div>
                            </div>
                            <span class="badge {{ $form->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $form->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            <a href="{{ route('whatsapp.third-party.forms.show', $form) }}" class="btn btn-sm rounded-pill btn-outline-secondary" title="Detail & script"><i class="ri-eye-line"></i></a>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">
                            <i class="ri-survey-line fs-2 d-block mb-1"></i>
                            Belum ada Google Form yang terhubung.
                            @if ($devices->isNotEmpty())
                                <div><a href="{{ route('whatsapp.third-party.forms.create', ['branch' => $branch->id]) }}">Hubungkan Google Form pertama Anda</a></div>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/wa-common.js') }}"></script>
@endpush
