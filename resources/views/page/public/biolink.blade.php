@php
    $color = $page->setting('biolink.color', '#111827');
    $links = collect($page->setting('biolink.links', []))->where('active', true);
    $targets = \App\Models\PageCategory::where('branch_id', $page->branch_id)->where('status', 'active')
        ->whereKey($links->pluck('page_id')->filter())->get(['id', 'slug'])->keyBy('id');
    $company = $page->branch?->company;
@endphp
<div class="text-center" style="max-width: 480px; margin: 0 auto;">
    @if ($company?->logoUrl())
        <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }}" width="88" height="88" class="rounded-circle border bg-white mb-3" style="object-fit: cover;">
    @endif
    <h1 class="h4 fw-semibold mb-1">{{ $page->name }}</h1>
    @if ($page->description)
        <p class="text-muted mb-4" style="white-space: pre-line;">{{ $page->description }}</p>
    @endif

    @forelse ($links as $link)
        @php($url = ! empty($link['page_id']) ? (($t = $targets->get($link['page_id'])) ? route('page.show', $t->slug) : null) : ($link['url'] ?? null))
        @if ($url)
            <a href="{{ $url }}" class="btn btn-lg w-100 mb-2 rounded-pill text-white fw-semibold" style="background: {{ $color }}; border-color: {{ $color }};"
                @if (empty($link['page_id'])) target="_blank" rel="noopener" @endif>{{ $link['label'] }}</a>
        @endif
    @empty
        <p class="text-muted">Belum ada link.</p>
    @endforelse
</div>
