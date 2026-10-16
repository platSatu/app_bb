@php
    $user = auth()->user();
    $tabs = array_filter([
        'deposits' => $user->canAccess('superadmin.deposits') ? ['Data Deposit', 'ri-wallet-3-line', 'superadmin.deposits.index'] : null,
        'history' => $user->canAccess('superadmin.deposits') ? ['History Saldo User', 'ri-history-line', 'superadmin.deposits.history'] : null,
        'setting' => $user->canAccess('superadmin.payment-gateway') ? ['Payment Gateway', 'ri-bank-card-line', 'superadmin.payment-gateway.setting'] : null,
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
