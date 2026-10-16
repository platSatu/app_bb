@extends('layouts.dashboard')

@section('title', 'Desain E-ticket')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@php($background = $page->setting('ticket.background') ? \Illuminate\Support\Facades\Storage::disk('public')->url($page->setting('ticket.background')) : null)

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-design'])

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-1 fw-semibold">Background E-ticket</h6>
                    <div class="text-muted small lh-sm">Opsional. Gambar banner event, disarankan landscape (mis. 1600×900), maksimal 5 MB.</div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form method="POST" action="{{ route($routes.'.ticket.design.update', $page) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <fieldset @disabled(! $canManage)>
                            <input type="file" name="background" accept="image/jpeg,image/png,image/webp" class="form-control mb-2 @error('background') is-invalid @enderror" id="background-input">
                            @error('background') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if ($background)
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="remove" value="1" id="remove">
                                    <label class="form-check-label" for="remove">Hapus background (pakai tampilan polos)</label>
                                </div>
                            @endif
                            @if ($canManage)
                                <button type="submit" class="btn btn-primary w-100 mt-2">Simpan</button>
                            @endif
                        </fieldset>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold">Preview</h6></div>
                <div class="card-body bg-light p-3 p-md-4">
                    @include('page.public._eticket-card', [
                        'background' => $background,
                        'eventName' => $page->name,
                        'eventDate' => $page->start_date?->translatedFormat('d F Y'),
                        'category' => 'VIP',
                        'holder' => 'Nama Pembeli',
                        'booking' => '123456',
                        'number' => 'BB12345678',
                        'sequence' => '1 dari 2',
                        'qr' => null,
                    ])
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('background-input')?.addEventListener('change', function (event) {
            var file = event.target.files[0];
            if (file) document.querySelector('[data-eticket-bg]').style.backgroundImage = 'url(' + URL.createObjectURL(file) + ')';
        });
    </script>
@endsection
