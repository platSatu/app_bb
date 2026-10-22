<ul class="nav nav-tabs mb-3">
    @foreach (['requests' => ['Pengajuan', 'ri-bank-line', 'superadmin.withdrawals.index'], 'accounts' => ['Verifikasi Rekening', 'ri-shield-check-line', 'superadmin.bank-accounts.index'], 'payouts' => ['Disbursement Owner', 'ri-send-plane-line', 'superadmin.payouts.index'], 'setting' => ['Duitku Disbursement', 'ri-settings-3-line', 'superadmin.disbursement.setting']] as $key => [$label, $icon, $route])
        <li class="nav-item">
            <a href="{{ route($route) }}" class="nav-link {{ $active === $key ? 'active' : '' }}" @if ($active === $key) aria-current="page" @endif>
                <i class="{{ $icon }} me-1"></i>{{ $label }}
            </a>
        </li>
    @endforeach
</ul>
