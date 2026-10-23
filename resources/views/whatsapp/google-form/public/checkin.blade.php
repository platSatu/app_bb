@extends('whatsapp.google-form.public.layout')

@section('title', 'Registrasi Ulang · '.$integration->name)
@section('width', '560px')

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body p-3 px-md-4 d-flex align-items-center justify-content-between gap-3">
            <div class="min-w-0">
                <div class="fw-bold text-truncate">{{ $integration->name }}</div>
                <div class="small text-muted">Hadir <b data-hadir>{{ $stats['hadir'] }}</b> dari <b data-total>{{ $stats['total'] }}</b> pendaftar</div>
            </div>
            <form method="POST" action="{{ route('form-checkin.logout', request()->route('token')) }}">
                @csrf
                <button class="btn btn-sm btn-light"><i class="ri-logout-box-r-line"></i> Keluar</button>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body p-4">
            <p class="small text-muted mb-2">Arahkan scanner ke QR pendaftar, atau ketik kode 10 huruf/angka lalu tekan Enter.</p>
            <form id="checkin-form" autocomplete="off">
                <input type="text" id="checkin-code" class="form-control form-control-lg text-center font-monospace" maxlength="255" autofocus placeholder="Scan QR atau ketik kode" style="letter-spacing: .1em;">
            </form>
        </div>
    </div>

    <div id="checkin-result" class="card border-0 shadow-sm rounded-4 text-center" hidden>
        <div class="card-body p-4">
            <span class="icon-circle mb-2"><i data-icon></i></span>
            <h2 class="h5 fw-bold mb-1" data-title></h2>
            <p class="mb-0 text-muted" data-text></p>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var input = document.getElementById('checkin-code'), box = document.getElementById('checkin-result'), busy = false;
            var looks = { ok: ['ri-checkbox-circle-line', '#16a34a'], duplicate: ['ri-error-warning-line', '#d97706'], unpaid: ['ri-bank-card-line', '#d97706'], invalid: ['ri-close-circle-line', '#dc2626'] };
            function show(data) {
                var look = looks[data.state] || looks.invalid;
                box.hidden = false;
                box.querySelector('[data-icon]').className = look[0];
                box.querySelector('.icon-circle').style.color = look[1];
                box.querySelector('[data-title]').textContent = data.title;
                box.querySelector('[data-text]').textContent = data.text;
                if (data.stats) {
                    document.querySelector('[data-hadir]').textContent = data.stats.hadir;
                    document.querySelector('[data-total]').textContent = data.stats.total;
                }
            }
            document.getElementById('checkin-form').addEventListener('submit', function (event) {
                event.preventDefault();
                var code = input.value.trim();
                if (!code || busy) return;
                busy = true;
                fetch({{ \Illuminate\Support\Js::from(route('form-checkin.check', request()->route('token'))) }}, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ code: code })
                }).then(function (r) {
                    if (r.status === 401 || r.status === 419) { location.reload(); return null; }
                    return r.json();
                }).then(function (data) { if (data) show(data); })
                .catch(function () { show({ state: 'invalid', title: 'Koneksi terputus', text: 'Periksa internet lalu coba lagi.' }); })
                .finally(function () { busy = false; input.value = ''; input.focus(); });
            });
            // Scanner selalu siap: klik di mana saja mengembalikan fokus ke kolom kode.
            document.addEventListener('click', function (e) { if (!e.target.closest('form[method=POST]')) input.focus(); });
        })();
    </script>
@endpush
