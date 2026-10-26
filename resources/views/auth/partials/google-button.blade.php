@if (config('services.google.client_id'))
    <div class="d-flex align-items-center gap-3 my-4 text-muted fs-13">
        <hr class="flex-grow-1 my-0"> atau <hr class="flex-grow-1 my-0">
    </div>
    <a href="{{ route('google.redirect') }}" class="btn btn-outline-light border text-body w-100 d-flex align-items-center justify-content-center gap-2">
        <i class="ri-google-fill fs-5 text-danger"></i> {{ $label ?? 'Masuk dengan Google' }}
    </a>
    <p class="text-muted fs-12 text-center mt-2 mb-0">
        Akun baru lewat Google berarti Anda menyetujui
        <a href="{{ config('services.frontend.url') }}/syarat-dan-ketentuan" target="_blank" rel="noopener">Syarat &amp; Ketentuan</a> dan
        <a href="{{ config('services.frontend.url') }}/page/kebijakan-privasi" target="_blank" rel="noopener">Kebijakan Privasi</a>.
    </p>
@endif
