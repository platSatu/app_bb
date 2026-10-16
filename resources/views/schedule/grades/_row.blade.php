<td>{{ $item->category?->subject?->name }} › {{ $item->category?->name }}</td>
<td class="text-nowrap">
    Rp {{ number_format($item->price, 0, ',', '.') }}
    <div class="text-muted small">{{ $item->meetings_per_month }}x pertemuan · {{ $item->duration_minutes }} menit</div>
</td>
<td class="text-nowrap small">
    <div class="fw-semibold">Rp {{ number_format($item->pricePerMeeting(), 0, ',', '.') }}</div>
    <div>Pengajar {{ rtrim(rtrim(number_format($item->teacher_percent, 2, ',', '.'), '0'), ',') }}%: Rp {{ number_format($item->teacherShare(), 0, ',', '.') }}</div>
    <div>Owner {{ rtrim(rtrim(number_format($item->ownerPercent(), 2, ',', '.'), '0'), ',') }}%: Rp {{ number_format($item->ownerShare(), 0, ',', '.') }}</div>
</td>
<td class="small">{{ $item->teachers->pluck('name')->join(', ') ?: '-' }}</td>
