<div class="table-responsive">
    <table class="table table-hover align-middle mb-0 text-nowrap">
        <thead class="table-light">
            <tr>
                @if ($showUser) <th>User</th> @endif
                <th>Sumber</th>
                <th>Arah</th>
                <th class="text-end">Nominal</th>
                <th class="text-end">Saldo Sebelum</th>
                <th class="text-end">Saldo Sesudah</th>
                <th>Keterangan</th>
                <th>Waktu</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transactions as $transaction)
                <tr>
                    @if ($showUser)
                        <td>
                            {{ $transaction->user?->name ?? '-' }}
                            <div class="text-muted small">{{ $transaction->user?->email }}</div>
                        </td>
                    @endif
                    <td>{{ $transaction->typeLabel() }}</td>
                    <td>
                        <span class="badge {{ $transaction->isCredit() ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                            {{ $transaction->isCredit() ? 'Masuk' : 'Keluar' }}
                        </span>
                    </td>
                    <td class="text-end fw-semibold {{ $transaction->isCredit() ? 'text-success' : 'text-danger' }}">
                        {{ $transaction->isCredit() ? '+' : '-' }}Rp {{ number_format($transaction->amount, 0, ',', '.') }}
                    </td>
                    <td class="text-end text-muted">Rp {{ number_format($transaction->balance_before, 0, ',', '.') }}</td>
                    <td class="text-end">Rp {{ number_format($transaction->balance_after, 0, ',', '.') }}</td>
                    <td class="text-muted">{{ $transaction->description ?: '-' }}</td>
                    <td class="text-muted">{{ $transaction->created_at->translatedFormat('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showUser ? 8 : 7 }}" class="text-center text-muted py-4">Belum ada riwayat saldo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
