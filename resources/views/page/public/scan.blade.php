@extends('page.public.layout', ['open' => false])

@section('title', 'Scan · '.$page->name)
@section('width', '640px')

@push('head')
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <div class="fw-semibold">{{ $page->name }}</div>
            <div class="small text-muted">Petugas {{ $scanner->name }}</div>
        </div>
        <form method="POST" action="{{ route('scan.logout', request()->route('token')) }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-light"><i class="ri-logout-box-r-line"></i> Keluar</button>
        </form>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-end">
                <div><div class="small text-muted">Sudah hadir</div><div class="fs-3 fw-semibold"><span id="stat-in">{{ $stats['checked_in'] }}</span> <span class="fs-6 text-muted">/ <span id="stat-total">{{ $stats['total'] }}</span></span></div></div>
                <div class="small text-muted text-end" id="stat-categories">
                    @foreach ($stats['categories'] as $row) <div>{{ $row['name'] }}: {{ $row['checked_in'] }}/{{ $row['total'] }}</div> @endforeach
                </div>
            </div>
        </div>
    </div>

    <form id="scan-form" class="mb-3" autocomplete="off">
        <input type="text" id="scan-input" class="form-control form-control-lg text-center" autofocus
            placeholder="Scan QR atau ketik kode booking, lalu Enter" maxlength="64">
    </form>

    <div id="result" class="card shadow-sm d-none">
        <div class="card-body text-center p-4">
            <i id="result-icon" class="fs-1"></i>
            <div id="result-message" class="fs-4 fw-semibold mt-1"></div>
            <div id="result-detail" class="text-muted mt-1"></div>
            <button type="button" id="confirm-button" class="btn btn-success btn-lg mt-3 d-none">Konfirmasi Hadir</button>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                var input = document.getElementById('scan-input');
                var result = document.getElementById('result');
                var confirmButton = document.getElementById('confirm-button');
                var styles = {
                    ok: ['border-success bg-success-subtle', 'ri-checkbox-circle-fill text-success'],
                    already: ['border-warning bg-warning-subtle', 'ri-error-warning-fill text-warning'],
                    confirm: ['border-primary bg-primary-subtle', 'ri-user-search-line text-primary'],
                    not_found: ['border-danger bg-danger-subtle', 'ri-close-circle-fill text-danger'],
                    error: ['border-danger bg-danger-subtle', 'ri-wifi-off-line text-danger']
                };
                var pendingCode = null, busy = false;

                function beep(ok) {
                    try {
                        var ctx = new (window.AudioContext || window.webkitAudioContext)(), osc = ctx.createOscillator();
                        osc.frequency.value = ok ? 880 : 220; osc.connect(ctx.destination); osc.start(); osc.stop(ctx.currentTime + 0.15);
                    } catch (e) {}
                }

                function show(data) {
                    var style = styles[data.status] || styles.error;
                    result.className = 'card shadow-sm border-2 ' + style[0];
                    document.getElementById('result-icon').className = 'fs-1 ' + style[1];
                    document.getElementById('result-message').textContent = data.message;
                    document.getElementById('result-detail').textContent = data.ticket
                        ? data.ticket.name + ' · ' + data.ticket.category + ' · Kode ' + data.ticket.booking_code : '';
                    confirmButton.classList.toggle('d-none', data.status !== 'confirm');
                    if (data.stats) {
                        document.getElementById('stat-in').textContent = data.stats.checked_in;
                        document.getElementById('stat-total').textContent = data.stats.total;
                        document.getElementById('stat-categories').innerHTML = data.stats.categories.map(function (c) {
                            var div = document.createElement('div'); div.textContent = c.name + ': ' + c.checked_in + '/' + c.total; return div.outerHTML;
                        }).join('');
                    }
                    if (data.status !== 'confirm') beep(data.status === 'ok');
                }

                function send(code, confirmed) {
                    if (busy) return;
                    busy = true;
                    fetch(@json(route('scan.check', request()->route('token'))), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ code: code, confirm: confirmed })
                    }).then(function (response) {
                        if (response.status === 401 || response.status === 419) { location.reload(); return null; }
                        return response.json();
                    }).then(function (data) {
                        if (!data) return;
                        pendingCode = data.status === 'confirm' ? code : null;
                        show(data);
                    }).catch(function () {
                        show({ status: 'error', message: 'Koneksi terputus. Coba scan lagi.' });
                    }).finally(function () {
                        busy = false;
                        input.value = '';
                        input.focus();
                    });
                }

                document.getElementById('scan-form').addEventListener('submit', function (event) {
                    event.preventDefault();
                    var code = input.value.trim();
                    if (code) send(code, false);
                });
                confirmButton.addEventListener('click', function () { if (pendingCode) send(pendingCode, true); });
                // Scanner USB/Bluetooth "mengetik" ke kotak input; fokus selalu dikembalikan.
                document.addEventListener('click', function (event) { if (event.target !== confirmButton) input.focus(); });
            })();
        </script>
    @endpush
@endsection
