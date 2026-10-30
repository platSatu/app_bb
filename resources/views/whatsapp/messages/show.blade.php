@extends('layouts.dashboard')

@section('title', $broadcast->name)
@section('page-section', 'Message')

@section('content')
    @php([$label, $color] = $broadcast->statusMeta())
    <div class="mb-3">
        <a href="{{ route('whatsapp.messages.index', ['branch' => $broadcast->branch_id]) }}" class="small"><i class="ri-arrow-left-line"></i> Kembali ke daftar Message</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1">{{ $broadcast->name }}</h5>
                            <div class="small text-muted">{{ \App\Models\WaBroadcast::TYPES[$broadcast->type] }} · dari {{ $broadcast->device?->name }}</div>
                        </div>
                        <span class="badge bg-{{ $color }}-subtle text-{{ $color }}">{{ $label }}</span>
                    </div>

                    @if ($broadcast->type === 'drip')
                        @foreach ($broadcast->steps as $step)
                            <div class="small fw-semibold mb-1">Pesan ke-{{ $loop->iteration }} · H+{{ $step->delay_days }}
                                @if ($broadcast->starts_at) <span class="text-muted fw-normal">({{ $broadcast->starts_at->copy()->addDays($step->delay_days)->translatedFormat('d M Y H:i') }})</span> @endif
                                @if ($loop->index < $broadcast->runs_count) <span class="badge bg-success-subtle text-success">terkirim</span> @endif
                            </div>
                            <div class="bg-light rounded p-2 mb-2 small" style="white-space: pre-wrap">{{ $step->body ?: '(tanpa teks)' }}@if ($step->upload_name)

📎 {{ $step->upload_name }}@endif</div>
                        @endforeach
                    @else
                        <div class="bg-light rounded p-3 mb-3 small" style="white-space: pre-wrap">{{ $broadcast->body ?: '(tanpa teks)' }}</div>
                        @if ($broadcast->upload_name)
                            <div class="small mb-3"><i class="ri-attachment-2"></i> {{ $broadcast->upload_name }}</div>
                        @endif
                        @if ($broadcast->template)
                            <div class="small text-muted mb-3"><i class="ri-file-list-3-line"></i> Dari template {{ $broadcast->template->name }}</div>
                        @endif
                    @endif

                    <ul class="list-unstyled small mb-3">
                        <li class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">Jadwal</span>
                            <span class="text-end">{{ \App\Models\WaBroadcast::SCHEDULES[$broadcast->schedule] ?? $broadcast->schedule }}
                                @if ($broadcast->type !== 'once' && $broadcast->starts_at)
                                    <div class="text-muted">mulai {{ $broadcast->starts_at->translatedFormat('d M Y H:i') }}{{ $broadcast->ends_at ? ' s/d '.$broadcast->ends_at->translatedFormat('d M Y') : '' }}</div>
                                @endif
                            </span>
                        </li>
                        <li class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">Kirim berikutnya</span><span>{{ $broadcast->status === 'active' ? ($broadcast->next_run_at?->translatedFormat('d M Y H:i') ?? '-') : '-' }}</span></li>
                        <li class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">Sudah dijalankan</span><span>{{ $broadcast->runs_count }}x</span></li>
                        <li class="d-flex justify-content-between py-1"><span class="text-muted">Penerima</span>
                            <span class="text-end">
                                @forelse ($targets as $line)
                                    <div>{{ $line }}</div>
                                @empty
                                    -
                                @endforelse
                                @if ($recipients !== null) <div class="text-muted">{{ $recipients }} tujuan saat ini</div> @endif
                            </span>
                        </li>
                    </ul>
                    @if ($stats)
                        <div class="d-flex flex-wrap gap-2 mb-3 small">
                            <span class="badge bg-secondary-subtle text-secondary">{{ (int) $stats->sent }} / {{ (int) $stats->total }} terkirim</span>
                            <span class="badge bg-info-subtle text-info">{{ (int) $stats->delivered }} delivered</span>
                            <span class="badge bg-success-subtle text-success">{{ (int) $stats->read }} read</span>
                            <span class="badge bg-danger-subtle text-danger">{{ (int) $stats->failed }} failed</span>
                        </div>
                    @endif
                    @if ($broadcast->last_error)
                        <div class="alert alert-warning small py-2">{{ $broadcast->last_error }}</div>
                    @endif

                    <div class="d-flex flex-wrap gap-2">
                        @if ($broadcast->status === 'active')
                            <form method="POST" action="{{ route('whatsapp.messages.pause', $broadcast) }}">@csrf<button class="btn btn-sm btn-outline-warning"><i class="ri-pause-line"></i> Jeda</button></form>
                        @elseif ($broadcast->status === 'paused')
                            <form method="POST" action="{{ route('whatsapp.messages.resume', $broadcast) }}">@csrf<button class="btn btn-sm btn-outline-success"><i class="ri-play-line"></i> Lanjutkan</button></form>
                        @endif
                        @if (in_array($broadcast->status, ['active', 'paused']))
                            <form method="POST" action="{{ route('whatsapp.messages.cancel', $broadcast) }}" onsubmit="return confirm('Batalkan pesan ini? Antrean yang belum terkirim tidak akan dikirim.')">@csrf<button class="btn btn-sm btn-outline-danger"><i class="ri-close-circle-line"></i> Batalkan</button></form>
                        @endif
                        <form method="POST" action="{{ route('whatsapp.messages.destroy', $broadcast) }}" onsubmit="return confirm('Hapus pesan beserta riwayat kirimnya?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-secondary"><i class="ri-delete-bin-line"></i> Hapus</button></form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Riwayat Kirim</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Waktu</th><th>Status</th><th style="min-width: 200px">Progres</th></tr></thead>
                            <tbody id="wa-runs" data-progress="{{ route('whatsapp.messages.progress', $broadcast) }}">
                                @forelse ($campaigns as $run)
                                    @php([$runLabel, $runColor] = $run->statusMeta())
                                    @php($pct = $run->total ? round(($run->sent + $run->failed) / $run->total * 100) : 0)
                                    <tr data-run="{{ $run->id }}" data-status="{{ $run->status }}">
                                        <td class="small text-nowrap">{{ $run->created_at->translatedFormat('d M Y H:i') }}</td>
                                        <td><span class="badge bg-{{ $runColor }}-subtle text-{{ $runColor }}" data-label>{{ $runLabel }}</span></td>
                                        <td>
                                            <div class="progress mb-1" style="height: 6px"><div class="progress-bar bg-success" data-bar style="width: {{ $pct }}%"></div></div>
                                            <div class="small text-muted" data-count>{{ $run->sent }} terkirim · {{ $run->failed }} gagal/dilewati · {{ $run->total }} total</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-3">Belum pernah dikirim.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($failures->isNotEmpty())
                <div class="card mb-0">
                    <div class="card-body">
                        <h6 class="mb-3">Gagal / Dilewati Terakhir</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Nomor</th><th>Keterangan</th><th>Waktu</th></tr></thead>
                                <tbody>
                                    @foreach ($failures as $fail)
                                        <tr>
                                            <td class="text-nowrap">+{{ $fail->chat_jid }}</td>
                                            <td class="small">{{ $fail->error ?: ($fail->status === 'skipped' ? 'Dilewati' : 'Gagal') }}</td>
                                            <td class="small text-nowrap text-muted">{{ \Illuminate\Support\Carbon::parse($fail->updated_at)->translatedFormat('d M H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Progres dimuat ulang tiap 10 detik selama masih ada pengiriman berjalan.
        (function () {
            var body = document.getElementById('wa-runs');
            var labels = @json(collect(\App\Models\WaCampaign::STATUSES)->map(fn ($s) => $s[0]));
            var live = function () { return body.querySelector('[data-status="running"], [data-status="draft"]'); };
            if (!live()) return;
            var timer = setInterval(function () {
                fetch(body.dataset.progress, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (data) {
                    (data.campaigns || []).forEach(function (c) {
                        var row = body.querySelector('[data-run="' + c.id + '"]');
                        if (!row) return;
                        row.dataset.status = c.status;
                        row.querySelector('[data-label]').textContent = labels[c.status] || c.status;
                        row.querySelector('[data-bar]').style.width = (c.total ? Math.round((c.sent + c.failed) / c.total * 100) : 0) + '%';
                        row.querySelector('[data-count]').textContent = c.sent + ' terkirim · ' + c.failed + ' gagal/dilewati · ' + c.total + ' total';
                    });
                    if (!live()) clearInterval(timer);
                });
            }, 10000);
        })();
    </script>
@endpush
