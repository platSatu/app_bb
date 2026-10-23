@extends('layouts.dashboard')

@section('title', 'Google Form')
@section('page-section', 'WhatsApp')

@php($tab = request('tab') === 'panduan' ? 'panduan' : 'daftar')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <p class="text-muted small mb-0">Setiap orang yang mengisi Google Form langsung dibalas WhatsApp. Bisa juga dengan pembayaran dan QR registrasi ulang.</p>
        @include('whatsapp._branch-filter')
    </div>

    @include('whatsapp._tabs', ['active' => $tab, 'tabs' => [
        'daftar' => ['Daftar Google Form', 'ri-file-list-3-line', route('whatsapp.google-forms.index', ['branch' => $branch->id])],
        'panduan' => ['Panduan', 'ri-book-open-line', route('whatsapp.google-forms.index', ['branch' => $branch->id, 'tab' => 'panduan'])],
    ]])

    @if ($tab === 'daftar')
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-end mb-3">
                    @if ($hasDevice)
                        <a href="{{ route('whatsapp.google-forms.create', ['branch' => $branch->id]) }}" class="btn btn-primary btn-sm"><i class="ri-link"></i> Hubungkan Google Form</a>
                    @else
                        <a href="{{ route('whatsapp.devices.index', ['branch' => $branch->id]) }}" class="btn btn-outline-primary btn-sm">Hubungkan nomor WhatsApp dulu</a>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Nama</th><th>Dikirim dari</th><th>Fitur</th><th class="text-center">Daftar</th><th class="text-center">Terkirim</th><th class="text-center">Hadir</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                            @forelse ($forms as $form)
                                <tr>
                                    <td><a href="{{ route('whatsapp.google-forms.show', $form) }}" class="fw-semibold text-body">{{ $form->name }}</a><div class="small text-muted">terakhir {{ $form->last_triggered_at?->diffForHumans() ?? 'belum ada' }}</div></td>
                                    <td class="small">{{ $form->device?->name ?? '-' }}</td>
                                    <td class="small text-nowrap">
                                        @if ($form->payment_enabled) <span class="badge bg-warning-subtle text-warning">Bayar Rp {{ number_format($form->amount, 0, ',', '.') }}</span> @endif
                                        @if ($form->registration_enabled) <span class="badge bg-info-subtle text-info">Registrasi ulang</span> @endif
                                        @if (! $form->payment_enabled && ! $form->registration_enabled) <span class="text-muted">Balas otomatis</span> @endif
                                    </td>
                                    <td class="text-center">{{ number_format($form->submissions_count) }}</td>
                                    <td class="text-center">{{ number_format($form->sent_count) }}</td>
                                    <td class="text-center">{{ $form->registration_enabled ? number_format($form->attended_count) : '-' }}</td>
                                    <td><span class="badge {{ $form->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $form->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                    <td class="text-end text-nowrap">
                                        <div class="d-inline-flex flex-nowrap gap-1">
                                            <a href="{{ route('whatsapp.google-forms.show', $form) }}" class="btn btn-sm rounded-pill btn-outline-secondary" title="Laporan"><i class="ri-bar-chart-box-line"></i></a>
                                            <a href="{{ route('whatsapp.google-forms.edit', $form) }}" class="btn btn-sm rounded-pill btn-outline-secondary" title="Ubah"><i class="ri-pencil-line"></i></a>
                                            <form method="POST" class="m-0" action="{{ route('whatsapp.google-forms.destroy', $form) }}" onsubmit="return confirm('Hapus integrasi ini beserta laporannya?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm rounded-pill btn-outline-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-4"><i class="ri-survey-line fs-2 d-block mb-1"></i>Belum ada Google Form yang terhubung. Baca tab <a href="{{ route('whatsapp.google-forms.index', ['branch' => $branch->id, 'tab' => 'panduan']) }}">Panduan</a> untuk mulai.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $forms->links() }}</div>
            </div>
        </div>
    @else
        @include('whatsapp.google-form._guide')
    @endif
@endsection
