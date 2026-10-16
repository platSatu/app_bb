{{-- Tab My Account. Tab baru: tambahkan 1 baris di sini + 1 file di profile/tabs. --}}
@php
    $tabs = [
        'profile' => ['label' => 'Profile', 'icon' => 'ri-user-line', 'route' => 'profile.edit'],
        'referral' => ['label' => 'Referral Saya', 'icon' => 'ri-gift-line', 'route' => 'profile.referral'],
        'balance' => ['label' => 'Saldo', 'icon' => 'ri-wallet-3-line', 'route' => 'profile.balance'],
        'withdrawal' => ['label' => 'Tarik Saldo', 'icon' => 'ri-bank-line', 'route' => 'profile.withdrawals'],
        'history' => ['label' => 'History', 'icon' => 'ri-history-line', 'route' => 'profile.history'],
    ];
@endphp

<ul class="nav nav-tabs mb-3">
    @foreach ($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ route($tab['route']) }}" class="nav-link {{ $active === $key ? 'active' : '' }}"
                @if ($active === $key) aria-current="page" @endif>
                <i class="{{ $tab['icon'] }} me-1"></i>{{ $tab['label'] }}
            </a>
        </li>
    @endforeach
</ul>
