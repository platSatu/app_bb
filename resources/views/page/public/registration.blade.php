<div class="card shadow-sm">
    <div class="card-body p-4 p-md-5">
        @if (session('submitted'))
            <div class="text-center">
                <i class="ri-checkbox-circle-line fs-1 text-success"></i>
                <h1 class="h4 mt-2">Terima kasih, data Anda sudah kami terima</h1>
                <p class="text-muted mb-1">Simpan nomor referensi berikut:</p>
                <p class="fs-4 fw-semibold mb-4">{{ session('submitted') }}</p>
                <a href="{{ $page->url() }}" class="btn btn-outline-secondary">Isi lagi</a>
            </div>
        @else
            <h1 class="h3 mb-2 text-center">{{ $page->name }}</h1>
            @if ($page->description)
                <p class="text-muted text-center mb-4" style="white-space: pre-line;">{{ $page->description }}</p>
            @endif

            @if ($page->fields->isNotEmpty())
                @if ($errors->any())
                    <div class="alert alert-danger">Ada isian yang belum sesuai, silakan periksa kembali.</div>
                @endif

                <form method="POST" action="{{ route('page.submit', $page->slug) }}" enctype="multipart/form-data"
                    onsubmit="this.querySelector('[type=submit]').disabled = true">
                    @csrf
                    @include('page.public._honeypot')
                    @include('page.public._fields', ['fields' => $page->fields, 'preview' => false])
                    @include('auth.partials.turnstile')
                    <button type="submit" class="btn btn-primary w-100">Kirim Pendaftaran</button>
                </form>
            @else
                <p class="text-center text-muted mb-0">Form pendaftaran sedang disiapkan.</p>
            @endif
        @endif
    </div>
</div>
