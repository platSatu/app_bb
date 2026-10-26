@php($rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'))
@if ($canManage && ! $freeFee['exempt'] && ! $freeFee['closed'] && $freeFee['quota'] > 0)
    @php($unused = max(0, $freeFee['paid_units'] - \App\Services\Ticket\FreeTicketFeeService::billableUnits($freeFee['used'], $freeFee['allowance'])))
    <form method="POST" action="{{ route($routes.'.ticket.free.close', $page) }}" class="card mt-3"
        onsubmit="return confirm('Tutup pendaftaran tiket gratis? Semua kategori gratis berhenti menerima pendaftar dan tidak bisa dibuka lagi.{{ $unused ? ' '.$unused.' tiket tidak terpakai ('.$rp($unused * $freeFee['unit_price']).') dikembalikan ke Saldo.' : '' }}')">
        @csrf
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="fw-semibold">Tutup pendaftaran tiket gratis</div>
                <div class="small text-muted">
                    Terdaftar {{ $freeFee['used'] }} dari {{ $freeFee['quota'] }} tiket gratis.
                    @if ($unused) Saat ditutup, {{ $unused }} tiket tidak terpakai ({{ $rp($unused * $freeFee['unit_price']) }}) kembali ke Saldo Anda. @endif
                </div>
            </div>
            <button class="btn btn-outline-danger btn-sm"><i class="ri-lock-line"></i> Tutup Pendaftaran</button>
        </div>
    </form>
@endif
