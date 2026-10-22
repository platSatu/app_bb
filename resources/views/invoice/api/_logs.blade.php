<div class="table-responsive">
    <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>Event</th><th>Invoice / Disbursement</th><th>Percobaan</th><th>Hasil</th><th>Waktu</th></tr></thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td><code>{{ $log->event }}</code></td>
                    <td class="small">{{ $log->invoice?->number ?? $log->disbursement?->reference ?? '-' }}</td>
                    <td class="small">ke-{{ $log->attempt }}</td>
                    <td class="small">
                        @if ($log->isSuccessful())
                            <span class="text-success"><i class="ri-check-line"></i> HTTP {{ $log->http_status }}</span>
                        @else
                            <span class="text-danger">{{ $log->error }}</span>
                        @endif
                    </td>
                    <td class="small text-muted text-nowrap">{{ $log->created_at->translatedFormat('d M H:i:s') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted small">Belum ada webhook terkirim.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
