@extends('layouts.dashboard')

@section('title', 'Edit Branch')
@section('page-section', 'Settings')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('settings.branches.update', $branch) }}">
                @csrf
                @method('PUT')
                @include('settings.branch._form')
            </form>
        </div>
    </div>

    {{-- Hanya tampil kalau paket WhatsApp branch aktif. Bawaan semua mati. --}}
    @if ($hasWhatsapp)
        <div class="card" id="notifikasi-wa">
            <div class="card-body">
                <h5 class="mb-1"><i class="ri-whatsapp-line text-success me-1"></i> Notifikasi WhatsApp</h5>
                <p class="text-muted small mb-3">Kirim pesan otomatis lewat nomor WhatsApp branch ini, isinya sama dengan email. Setiap pesan memakai kuota paket WhatsApp.</p>

                @unless ($waConnected)
                    <div class="alert alert-warning small py-2">Nomor WhatsApp branch ini belum terhubung, jadi pesan belum bisa dikirim. Hubungkan dulu di menu WhatsApp › Device &amp; Inbox.</div>
                @endunless

                <form method="POST" action="{{ route('settings.branches.wa-notify', $branch) }}">
                    @csrf
                    @method('PUT')
                    @foreach (\App\Models\UserBranch::WA_NOTIFY as $kind => [$label, $hint])
                        <div class="d-flex align-items-center gap-3 py-3 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div class="flex-grow-1">
                                <label for="wa-{{ $kind }}" class="fw-semibold mb-0">{{ $label }}</label>
                                <div class="small text-muted">{{ $hint }}</div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input type="checkbox" role="switch" class="form-check-input" style="width: 2.5em; height: 1.3em" id="wa-{{ $kind }}" name="wa_notify[{{ $kind }}]" value="1" @checked(! empty($branch->wa_notify[$kind]))>
                            </div>
                        </div>
                    @endforeach
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-success"><i class="ri-save-line me-1"></i>Simpan Notifikasi</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
