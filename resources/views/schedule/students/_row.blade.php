<td class="small text-nowrap">
    <div>{{ $item->phone ? '+'.$item->phone : '-' }}</div>
    <div class="text-muted">{{ $item->email }}</div>
</td>
<td>
    {{ $item->guardian_name ?: '-' }}
    @if ($item->left_on) <div class="small text-danger">Keluar {{ $item->left_on->translatedFormat('d M Y') }}</div> @endif
</td>
