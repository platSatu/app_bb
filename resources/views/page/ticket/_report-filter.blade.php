{{-- Tab + filter + tombol Export. $tabs [key => label], $statuses [key => label]|null, $searchPlaceholder|null. --}}
<ul class="nav nav-pills gap-2 mb-3">
    @foreach ($tabs as $key => $label)
        <li class="nav-item">
            <a href="{{ request()->fullUrlWithQuery(['tab' => $key, 'status' => null, 'search' => null, 'page' => null, 'export' => null]) }}"
                class="nav-link py-1 px-3 {{ $filters['tab'] === $key ? 'active' : 'bg-light text-body' }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>

@if ($errors->any())
    <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
@endif

<form method="GET" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="tab" value="{{ $filters['tab'] }}">
    @if ($searchPlaceholder ?? null)
        <div class="col-12 col-lg">
            <label class="form-label small mb-1">Cari</label>
            <input type="text" name="search" class="form-control form-control-sm" maxlength="100" placeholder="{{ $searchPlaceholder }}" value="{{ $filters['search'] }}">
        </div>
    @endif
    @if ($statuses ?? null)
        <div class="col-6 col-lg-3">
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Semua</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-6 col-lg-2">
        <label class="form-label small mb-1">Dari tanggal</label>
        <input type="date" name="from" class="form-control form-control-sm" value="{{ $filters['from'] }}">
    </div>
    <div class="col-6 col-lg-2">
        <label class="form-label small mb-1">Sampai tanggal</label>
        <input type="date" name="to" class="form-control form-control-sm" value="{{ $filters['to'] }}">
    </div>
    <div class="col-6 col-lg-auto d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-outline-secondary flex-grow-1"><i class="ri-filter-3-line"></i> Terapkan</button>
        @if ($filters['search'] || $filters['status'] || $filters['from'] || $filters['to'])
            <a href="{{ url()->current() }}?tab={{ $filters['tab'] }}" class="btn btn-sm btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
        @endif
    </div>
    <div class="col-12 col-lg-auto ms-lg-auto">
        <a href="{{ request()->fullUrlWithQuery(['export' => 1, 'page' => null]) }}" class="btn btn-sm btn-success w-100"><i class="ri-file-excel-2-line me-1"></i>Export Excel</a>
    </div>
</form>
