<td>{{ $item->category?->subject?->name }} › {{ $item->category?->name }}</td>
<td class="text-nowrap">
    <span class="badge {{ $item->isPackage() ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info' }} mb-1">{{ $item->isPackage() ? 'Paket' : 'Bulanan' }}</span>
    <div>Rp {{ number_format($item->price, 0, ',', '.') }}{{ $item->isPackage() ? '' : ' / bulan' }}</div>
    <div class="text-muted small">{{ $item->meetings_per_month }}x pertemuan{{ $item->isPackage() ? ' (privat)' : '' }} · {{ $item->duration_minutes }} menit</div>
</td>
<td class="text-nowrap small">
    <div class="fw-semibold">Rp {{ number_format($item->pricePerMeeting(), 0, ',', '.') }}</div>
    <div>Pengajar {{ rtrim(rtrim(number_format($item->teacher_percent, 2, ',', '.'), '0'), ',') }}%: Rp {{ number_format($item->teacherShare(), 0, ',', '.') }}</div>
    <div>Owner {{ rtrim(rtrim(number_format($item->ownerPercent(), 2, ',', '.'), '0'), ',') }}%: Rp {{ number_format($item->ownerShare(), 0, ',', '.') }}</div>
</td>
<td class="small">{{ $item->teachers->pluck('name')->join(', ') ?: '-' }}</td>
