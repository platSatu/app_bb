@extends('layouts.dashboard')

@section('title', 'Syarat dan Ketentuan')
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-terms'])

    <form method="POST" action="{{ route($routes.'.ticket.terms.update', $page) }}">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h6 class="mb-1 fw-semibold">Syarat & Ketentuan</h6>
                    <div class="text-muted small lh-sm">Berlaku untuk semua tiket di page ini. Kalau diisi, pembeli wajib mencentang persetujuan sebelum bisa membayar. Kosongkan kalau tidak perlu.</div>
                </div>
                @if ($canManage)
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="ri-save-line me-1"></i>Simpan</button>
                @endif
            </div>
            <div class="card-body p-3 p-md-4">
                <fieldset @disabled(! $canManage)>
                    <textarea name="terms" rows="16" maxlength="{{ \App\Http\Controllers\Page\Ticket\TicketTermsController::MAX }}"
                        class="form-control @error('terms') is-invalid @enderror"
                        placeholder="Contoh:&#10;1. Tiket yang sudah dibeli tidak dapat dikembalikan.&#10;2. Satu tiket berlaku untuk satu orang.&#10;3. Tunjukkan e-ticket saat registrasi ulang.">{{ old('terms', $page->setting('ticket.terms')) }}</textarea>
                    @error('terms') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Tulis per poin, satu poin per baris. Baris baru akan tetap tampil di halaman pembeli.</div>
                </fieldset>
            </div>
        </div>
    </form>
@endsection
