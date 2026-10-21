@extends('layouts.dashboard')

@section('title', 'Detail Pesanan Tiket')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-orders'])

    @if ($order->status === \App\Models\TicketOrder::STATUS_REFUND_NEEDED)
        <div class="alert alert-danger"><i class="ri-error-warning-line me-1"></i>{{ $order->failure_reason }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h6 class="mb-1 fw-semibold">{{ $order->reference }}</h6>
                        <div class="text-muted small lh-sm">Dibuat {{ $order->created_at->translatedFormat('d M Y H:i') }}{{ $order->paid_at ? ' · dibayar '.$order->paid_at->translatedFormat('d M Y H:i') : '' }}{{ $order->payment_method ? ' · '.$order->payment_method : '' }}</div>
                    </div>
                    <span class="badge {{ $order->statusBadge() }}">{{ $order->statusLabel() }}</span>
                </div>
                <div class="card-body p-3 p-md-4">
                    <table class="table align-middle mb-4">
                        <thead class="table-light"><tr><th>Kategori</th><th class="text-end">Harga</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th></tr></thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr><td>{{ $item->name }}</td><td class="text-end">Rp {{ number_format($item->price, 0, ',', '.') }}</td><td class="text-center">{{ $item->quantity }}</td><td class="text-end">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end">Rp {{ number_format($order->total, 0, ',', '.') }}</th></tr></tfoot>
                    </table>

                    @if ($order->tickets->isNotEmpty())
                        <h6 class="fw-semibold mb-2">E-ticket ({{ $order->tickets->count() }})</h6>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light"><tr><th>#</th><th>No. Tiket</th><th>Kategori</th><th>Kode Booking</th><th>Registrasi Ulang</th></tr></thead>
                                <tbody>
                                    @foreach ($order->tickets as $ticket)
                                        <tr>
                                            <td>{{ $ticket->sequence }}</td>
                                            <td><a href="{{ $ticket->url($page->slug) }}" target="_blank" rel="noopener noreferrer">{{ $ticket->number }}</a></td>
                                            <td>{{ $ticket->category_name }}</td>
                                            <td class="fw-semibold">{{ $ticket->booking_code }}</td>
                                            <td>{!! $ticket->isCheckedIn() ? '<span class="badge bg-success-subtle text-success">Hadir '.e($ticket->checked_in_at->translatedFormat('d M H:i')).'</span>' : '<span class="text-muted small">Belum</span>' !!}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif ($order->isPending())
                        <div class="text-muted"><i class="ri-time-line"></i> Menunggu pembayaran sampai {{ $order->expires_at->translatedFormat('d M Y H:i') }}. Kuota tiket sedang ditahan.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold">Pembeli</h6></div>
                <div class="card-body p-3 p-md-4">
                    <dl class="mb-0">
                        <dt class="text-muted fw-normal small">Nama</dt><dd>{{ $order->name }}</dd>
                        <dt class="text-muted fw-normal small">Email</dt><dd>{{ $order->email }}</dd>
                        <dt class="text-muted fw-normal small">WhatsApp</dt><dd class="mb-0">{{ $order->phone }}</dd>
                    </dl>
                    @if ($order->isPaid())
                        <hr>
                        <div class="small text-muted mb-2">
                            Email e-ticket: {{ $order->email_sent_at ? 'dikirim '.$order->email_sent_at->translatedFormat('d M Y H:i') : 'belum terkirim' }}
                        </div>
                        @if ($canManage)
                            <form method="POST" action="{{ route($routes.'.ticket.orders.resend', [$page, $order]) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="ri-mail-send-line me-1"></i>Kirim Ulang E-ticket</button>
                            </form>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2" data-copy="{{ $order->url() }}"><i class="ri-link me-1"></i>Salin Link E-ticket</button>
                    @endif
                </div>
            </div>
            @if ($order->submission)
                <div class="card">
                    <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold">Data Tambahan</h6></div>
                    <div class="card-body p-3 p-md-4">
                        <dl class="mb-0">
                            @foreach ($order->submission->answers as $answer)
                                <dt class="text-muted fw-normal small">{{ $answer->label }}</dt>
                                <dd class="{{ $loop->last ? 'mb-0' : '' }}" style="white-space: pre-line;">
                                    @if ($answer->file_path)
                                        <a href="{{ route($routes.'.submissions.file', [$page, $order->submission, $answer]) }}"><i class="ri-download-2-line"></i> {{ $answer->file_name }}</a>
                                    @else
                                        {{ filled($answer->value) ? $answer->value : '-' }}
                                    @endif
                                </dd>
                            @endforeach
                        </dl>
                    </div>
                </div>
            @endif
            <a href="{{ route($routes.'.ticket.orders.index', $page) }}" class="btn btn-light w-100"><i class="ri-arrow-left-line"></i> Kembali</a>
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                navigator.clipboard.writeText(button.dataset.copy).then(function () { button.innerHTML = '<i class="ri-check-line me-1"></i>Link disalin'; });
            });
        });
    </script>
@endsection
