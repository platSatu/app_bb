@php
    $tabs = $isAdmin
        ? ['invoices' => ['List Invoice', 'ri-file-list-3-line', 'superadmin.invoices.index'], 'setting' => ['Biaya Invoice', 'ri-percent-line', 'superadmin.invoices.settings']]
        : [
            'invoices' => ['Semua Invoice', 'ri-file-list-3-line', 'invoices.index'],
            'batches' => ['Invoice Periode', 'ri-calendar-check-line', 'invoices.batches.index'],
            'categories' => ['Kategori', 'ri-price-tag-3-line', 'invoices.categories.index'],
            'api' => ['API & Integrasi', 'ri-plug-line', 'invoices.api.index'],
            'docs' => ['Dokumentasi', 'ri-book-open-line', 'invoices.docs'],
        ];
    if ($isAdmin && ! auth()->user()->canAccess('superadmin.invoice-settings', 'manage')) unset($tabs['setting']);
@endphp

<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto text-nowrap">
    @foreach ($tabs as $key => [$label, $icon, $route])
        <li class="nav-item">
            <a href="{{ route($route) }}" class="nav-link {{ $tab === $key ? 'active' : '' }}" @if ($tab === $key) aria-current="page" @endif>
                <i class="{{ $icon }} me-1"></i>{{ $label }}
            </a>
        </li>
    @endforeach
</ul>
