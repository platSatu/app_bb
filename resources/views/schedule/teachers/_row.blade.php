<td class="small text-nowrap">
    <div>{{ $item->phone ? '+'.$item->phone : '-' }}</div>
    <div class="text-muted">{{ $item->email }}</div>
</td>
<td class="small">
    @forelse ($item->grades as $grade)
        <span class="badge bg-light text-dark border mb-1">{{ $grade->label() }}</span>
    @empty
        <span class="text-muted">Belum dipilih</span>
    @endforelse
</td>
