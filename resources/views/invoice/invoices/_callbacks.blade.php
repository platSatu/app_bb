{{-- Data pembanding: callback masuk dari Duitku vs callback (webhook) keluar ke website customer. --}}
@php($json = fn ($data) => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))

<div class="card mt-3">
    <div class="card-body">
        <h6 class="mb-1">Callback dari Duitku</h6>
        <p class="text-muted small mb-3">Nominal yang dikirim Duitku dibandingkan dengan nominal order yang diminta bizbos.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light text-nowrap">
                    <tr>
                        <th>Waktu</th><th>Order Duitku</th><th class="text-end">Nominal bizbos</th><th class="text-end">Nominal Duitku</th>
                        <th>Kode Hasil</th><th>Ref. Duitku</th><th>Metode</th><th>Tanda Tangan</th><th>Hasil Proses</th><th></th>
                    </tr>
                </thead>
                <tbody class="text-nowrap small">
                    @forelse ($callbacks as $callback)
                        @php($expected = $orders[$callback->merchant_order_id] ?? null)
                        @php($code = $callback->payload['resultCode'] ?? null)
                        <tr>
                            <td class="text-muted">{{ $callback->created_at->translatedFormat('d M Y H:i:s') }}</td>
                            <td>{{ $callback->merchant_order_id }}</td>
                            <td class="text-end">{{ $expected !== null ? $rp($expected) : '-' }}</td>
                            <td class="text-end {{ $expected !== null && (int) $expected !== $callback->amount() ? 'text-danger fw-semibold' : '' }}">{{ $rp($callback->amount()) }}</td>
                            <td>{{ $code === '00' ? 'Sukses (00)' : ($code !== null ? 'Gagal ('.$code.')' : '-') }}</td>
                            <td>{{ $callback->payload['reference'] ?? '-' }}</td>
                            <td>{{ $callback->payload['paymentCode'] ?? '-' }}</td>
                            <td>{!! $callback->signature_valid ? '<span class="text-success"><i class="ri-check-line"></i> Valid</span>' : '<span class="text-danger"><i class="ri-close-line"></i> Tidak valid</span>' !!}</td>
                            <td><span class="badge {{ $callback->resultBadge() }}">{{ $callback->resultLabel() }}</span></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#cb-{{ $callback->id }}">Data</button></td>
                        </tr>
                        <tr class="collapse" id="cb-{{ $callback->id }}">
                            <td colspan="10" class="bg-light">
                                <div class="small text-muted mb-1">IP pengirim: {{ $callback->ip_address ?? '-' }}{{ $callback->message ? ' · '.$callback->message : '' }}</div>
                                <pre class="small mb-0">{{ $json($callback->payload) }}</pre>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-muted">Belum ada callback dari Duitku untuk invoice ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-0">
    <div class="card-body">
        <h6 class="mb-1">Callback ke Website Customer</h6>
        @if (! $invoice->api_client_id)
            <p class="text-muted small mb-0">Invoice ini dibuat dari dashboard, jadi tidak ada callback ke website customer.</p>
        @else
            <p class="text-muted small mb-3">Data yang bizbos kirim ke website customer. Angka merah = beda dengan data invoice saat ini.</p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light text-nowrap">
                        <tr>
                            <th>Waktu</th><th>Event</th><th>Percobaan</th><th>Jawaban Server</th><th>Status Dikirim</th>
                            <th class="text-end">Tagihan</th><th class="text-end">Fee</th><th class="text-end">Jumlah yang Diterima</th><th></th>
                        </tr>
                    </thead>
                    <tbody class="text-nowrap small">
                        @forelse ($webhooks as $log)
                            @php($data = $log->payloadData())
                            {{-- Hanya event invoice.paid yang wajib sama persis dengan angka lunas di invoice. --}}
                            @php($check = $log->event === 'invoice.paid' && $invoice->isPaid())
                            <tr>
                                <td class="text-muted">{{ $log->created_at->translatedFormat('d M Y H:i:s') }}</td>
                                <td><code>{{ $log->event }}</code></td>
                                <td>ke-{{ $log->attempt }}</td>
                                <td>
                                    @if ($log->isSuccessful())
                                        <span class="text-success"><i class="ri-check-line"></i> HTTP {{ $log->http_status }}</span>
                                    @else
                                        <span class="text-danger">{{ \Illuminate\Support\Str::limit($log->error, 60) }}</span>
                                    @endif
                                </td>
                                <td>{{ $data['status'] ?? '-' }}</td>
                                @foreach (['total' => $invoice->paid_amount, 'fee' => $invoice->fee, 'net_amount' => $invoice->net_amount] as $key => $actual)
                                    <td class="text-end {{ $check && isset($data[$key]) && (int) $data[$key] !== (int) $actual ? 'text-danger fw-semibold' : '' }}">{{ isset($data[$key]) ? $rp($data[$key]) : '-' }}</td>
                                @endforeach
                                <td class="text-end">
                                    @if ($log->payload)
                                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#wh-{{ $log->id }}">Data</button>
                                    @endif
                                </td>
                            </tr>
                            @if ($log->payload)
                                <tr class="collapse" id="wh-{{ $log->id }}">
                                    <td colspan="9" class="bg-light">
                                        <div class="small text-muted mb-1">Dikirim:</div>
                                        <pre class="small">{{ $json(json_decode($log->payload, true)) }}</pre>
                                        <div class="small text-muted mb-1">Jawaban server customer:</div>
                                        <pre class="small mb-0">{{ $log->response_body ?: '(kosong)' }}</pre>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="9" class="text-muted">Belum ada callback terkirim.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
