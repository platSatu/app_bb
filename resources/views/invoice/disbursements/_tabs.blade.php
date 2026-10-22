<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto text-nowrap">
    @foreach (['history' => ['Riwayat', 'ri-history-line', 'invoices.disbursements.index'], 'recipients' => ['Rekening Penerima', 'ri-bank-line', 'invoices.disbursements.recipients'], 'settings' => ['Pengaturan & API', 'ri-settings-3-line', 'invoices.disbursements.settings']] as $key => [$label, $icon, $route])
        <li class="nav-item">
            <a href="{{ route($route, ['branch' => $branch->id]) }}" class="nav-link {{ $tab === $key ? 'active' : '' }}" @if ($tab === $key) aria-current="page" @endif>
                <i class="{{ $icon }} me-1"></i>{{ $label }}
            </a>
        </li>
    @endforeach
</ul>

@if ($branches->count() > 1)
    <form method="GET" class="mb-3 d-flex gap-2 align-items-center">
        <label class="small text-muted">Branch</label>
        <select name="branch" class="form-select w-auto" onchange="this.form.submit()">
            @foreach ($branches as $option)
                <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    </form>
@endif

@unless ($available)
    <div class="alert alert-warning"><i class="ri-error-warning-line me-1"></i>Disbursement sedang tidak tersedia. Riwayat tetap bisa dilihat, pengiriman dana baru menunggu diaktifkan tim bizbos.</div>
@endunless
