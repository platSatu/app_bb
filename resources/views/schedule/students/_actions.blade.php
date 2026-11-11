<a href="{{ route($routes.'.routines.index', ['student' => $item->id]) }}" class="btn btn-sm rounded-pill btn-outline-primary"><i class="ri-calendar-2-line"></i> Jadwal</a>
@if ($item->isActive())
    {{-- Kartu absen QR: dibuka/dicetak di sini, atau dikirim ke email & WhatsApp murid. --}}
    @php($waAuto = $canManage && $item->phone && isset(\App\Http\Controllers\Schedule\StudentController::waDevices()[$item->branch_id]))
    <div class="dropdown d-inline-block">
        <button type="button" class="btn btn-sm rounded-pill btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">
            <i class="ri-qr-code-line"></i> Kartu QR
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li><a class="dropdown-item" href="{{ $item->cardUrl() }}" target="_blank" rel="noopener"><i class="ri-eye-line me-2"></i>Lihat & cetak kartu</a></li>
            @if ($canManage)
                <li>
                    <form method="POST" action="{{ route($routes.'.students.card', $item) }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <input type="hidden" name="via" value="email">
                        <button type="submit" class="dropdown-item" @disabled(! $item->email)><i class="ri-mail-send-line me-2"></i>Kirim ke email{{ $item->email ? '' : ' (belum ada email)' }}</button>
                    </form>
                </li>
            @endif
            @if ($waAuto)
                <li>
                    <form method="POST" action="{{ route($routes.'.students.card', $item) }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <input type="hidden" name="via" value="whatsapp">
                        <button type="submit" class="dropdown-item"><i class="ri-whatsapp-line me-2"></i>Kirim otomatis lewat WhatsApp</button>
                    </form>
                </li>
            @endif
            @if ($item->phone)
                <li><a class="dropdown-item" href="https://wa.me/{{ $item->phone }}?text={{ rawurlencode($item->cardMessage()) }}" target="_blank" rel="noopener"><i class="ri-share-forward-line me-2"></i>Kirim manual lewat WhatsApp</a></li>
            @else
                <li><span class="dropdown-item-text small text-muted">Tambahkan nomor WhatsApp untuk kirim lewat WA.</span></li>
            @endif
        </ul>
    </div>
@endif
