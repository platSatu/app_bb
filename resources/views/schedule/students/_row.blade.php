<td class="small text-nowrap">
    <div>{{ $item->phone ? '+'.$item->phone : '-' }}</div>
    <div class="text-muted">{{ $item->email }}</div>
</td>
<td>{{ $item->guardian_name ?: '-' }}</td>
