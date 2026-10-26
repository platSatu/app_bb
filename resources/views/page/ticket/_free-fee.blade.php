{{--
    Biaya tiket gratis (lihat FreeTicketFeeService). Angka di sini hanya perkiraan untuk tampilan;
    yang dipotong tetap dihitung ulang server, dan harus sama dengan nominal yang disetujui (free_fee_confirm).
--}}
@php($rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'))
@unless ($freeFee['exempt'])
    <div class="border rounded-3 bg-white p-3 mb-3" id="free-fee"
        data-unit="{{ $freeFee['unit_price'] }}" data-allowance="{{ $freeFee['allowance'] }}" data-paid="{{ $freeFee['paid_units'] }}" data-closed="{{ $freeFee['closed'] ? 1 : 0 }}">
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <div class="fw-semibold"><i class="ri-gift-line text-primary"></i> Biaya tiket gratis</div>
            @if ($freeFee['closed'])
                <span class="badge bg-secondary-subtle text-secondary">Pendaftaran gratis sudah ditutup</span>
            @endif
        </div>
        <div class="small text-muted mt-1">
            {{ $freeFee['allowance'] }} tiket gratis pertama di event ini tidak dikenai biaya. Selebihnya {{ $rp($freeFee['unit_price']) }} per tiket, dibayar di depan dari Saldo saat Anda menyimpan.
            Tiket yang tidak terpakai dikembalikan ke Saldo saat Anda menutup pendaftaran.
        </div>
        @unless ($freeFee['closed'])
            <div class="small mt-2" data-free-summary></div>
        @endunless
        <input type="hidden" name="free_fee_confirm" value="0" data-free-confirm>
    </div>
@endunless
