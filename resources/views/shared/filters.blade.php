<form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-12 col-md-4 col-xl-3">
        <label class="form-label small text-muted mb-1">Cari</label>
        <input type="text" name="search" class="form-control" maxlength="100" placeholder="{{ $placeholder }}" value="{{ request('search') }}">
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">{{ $selectLabel }}</label>
        <select name="{{ $selectName }}" class="form-select">
            <option value="">Semua</option>
            @foreach ($selectOptions as $value => $label)
                <option value="{{ $value }}" @selected(request($selectName) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">Dari tanggal</label>
        <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small text-muted mb-1">Sampai tanggal</label>
        <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
    </div>
    <div class="col-6 col-md-3 col-xl-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1"><i class="ri-search-line"></i> Filter</button>
        @if (request()->hasAny(['search', $selectName, 'date_from', 'date_to']))
            <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
        @endif
    </div>
</form>
