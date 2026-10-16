<td>
    {{ $item->subject?->name ?? '-' }}
    <div class="text-muted small">{{ $item->subject?->room?->name }}</div>
</td>
