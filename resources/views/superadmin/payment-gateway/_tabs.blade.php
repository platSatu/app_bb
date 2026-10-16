@php
    $canManage = auth()->user()->canAccess('superadmin.payment-gateway', \App\Support\Menu::ABILITY_MANAGE);
    $tabs = array_filter([
        'setting' => $canManage ? ['Pengaturan', 'ri-settings-3-line', 'superadmin.payment-gateway.setting'] : null,
        'deposits' => ['Data Deposit', 'ri-wallet-3-line', 'superadmin.payment-gateway.deposits'],
        'history' => ['History Saldo User', 'ri-history-line', 'superadmin.payment-gateway.history'],
    ]);
@endphp

<ul class="nav nav-tabs mb-3">
    @foreach ($tabs as $key => [$label, $icon, $route])
        <li class="nav-item">
            <a href="{{ route($route) }}" class="nav-link {{ $active === $key ? 'active' : '' }}" @if ($active === $key) aria-current="page" @endif>
                <i class="{{ $icon }} me-1"></i>{{ $label }}
            </a>
        </li>
    @endforeach
</ul>
