@extends('layouts.dashboard')

@section('title', $config['label'])
@section('page-section', $isAdmin ? 'Superadmin' : 'Schedules')

@php
    $parent = isset($config['parent']) ? $resources[$config['parent']] : null;
    $childConfig = $child ? $resources[$child] : null;
    $showBranch = $isAdmin || $branches->count() > 1;
    $filtered = request()->hasAny(['search', 'status', 'parent', 'branch']);
@endphp

@section('content')
    @include('schedule._tabs')

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center mb-3">
                <div class="col-12 col-md">
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari {{ strtolower($config['label']) }}" value="{{ request('search') }}">
                </div>
                @if ($parent && $parentOptions->isNotEmpty())
                    <div class="col-12 col-md-3">
                        <select name="parent" class="form-select">
                            <option value="">Semua {{ strtolower($parent['label']) }}</option>
                            @foreach ($parentOptions as $option)
                                <option value="{{ $option->id }}" @selected(request('parent') === $option->id)>{{ $option->label() }}{{ $branches->count() > 1 ? ' — '.$option->branch?->name : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($parentFilter)
                    <input type="hidden" name="parent" value="{{ $parentFilter->id }}">
                @endif
                @if ($branches->count() > 1)
                    <div class="col-12 col-md-2">
                        <select name="branch" class="form-select">
                            <option value="">Semua branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(request('branch') === $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-12 col-md-2">
                    <select name="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach ($config['model']::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line"></i> Cari</button>
                    @if ($filtered)
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    @if ($parentFilter)
                        <span class="badge bg-primary-subtle text-primary fs-13">
                            {{ $parent['label'] }}: {{ $parentFilter->label() }}
                            <a href="{{ route($routes.'.'.$resource.'.index') }}" class="text-primary ms-1" title="Tampilkan semua"><i class="ri-close-line"></i></a>
                        </span>
                    @endif
                </div>
                @if ($canManage)
                    <a href="{{ route($routes.'.'.$resource.'.create', array_filter(['parent' => $parentFilter?->id])) }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Tambah {{ $config['label'] }}
                    </a>
                @endif
            </div>

            {{-- Murid: centang lalu Buat Tagihan, invoice les langsung masuk menu Invoice (hanya kalau paket Invoice aktif). --}}
            @php($billing = $resource === 'students' && $canManage && $items->isNotEmpty()
                && \App\Models\UserBranch::whereKey($items->pluck('branch_id')->unique())->whereIn('id', \App\Services\Subscription\AppAccess::activeBranches('invoices'))->exists())
            @if ($billing)
                <form method="POST" action="{{ route($routes.'.students.bill') }}" id="bill-form" class="d-flex flex-wrap align-items-center gap-3 border rounded-3 p-3 mb-3"
                    onsubmit="this.querySelector('button').disabled = true;">
                    @csrf
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><i class="ri-bill-line text-primary me-1"></i> Tagihan les</div>
                        <div class="small text-muted">Centang murid, pilih bulan, lalu tagihan dibuat otomatis di menu Invoice per kelas. Nama kategori & periode mengikuti Bidang › Kategori › Grade, nominal mengikuti harga grade.</div>
                    </div>
                    <input type="month" name="month" class="form-control" style="width: 11rem" value="{{ today()->format('Y-m') }}" required aria-label="Bulan tagihan">
                    <button type="submit" class="btn btn-primary" data-bill-submit disabled title="Centang murid dulu"><i class="ri-file-add-line me-1"></i>Buat Tagihan (<span data-bill-count>0</span>)</button>
                </form>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            @if ($billing)
                                <th style="width: 2.5rem"><input type="checkbox" class="form-check-input" data-bill-all title="Pilih semua di halaman ini" aria-label="Pilih semua murid"></th>
                            @endif
                            <th>{{ $config['label'] }}</th>
                            @foreach ($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                            @if ($showBranch)
                                <th>{{ $isAdmin ? 'Perusahaan / Branch' : 'Branch' }}</th>
                            @endif
                            @if ($childConfig)
                                <th class="text-center">{{ $childConfig['label'] }}</th>
                            @endif
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                @if ($billing)
                                    <td><input type="checkbox" class="form-check-input" name="student_ids[]" value="{{ $item->id }}" form="bill-form" data-bill aria-label="Pilih {{ $item->name }}"></td>
                                @endif
                                <td>
                                    <div class="fw-semibold">{{ $item->name }}</div>
                                    @if ($item->description)
                                        <div class="text-muted small text-truncate" style="max-width: 280px">{{ $item->description }}</div>
                                    @endif
                                </td>
                                @includeIf('schedule.'.$resource.'._row')
                                @if ($showBranch)
                                    <td>
                                        @if ($isAdmin)
                                            <div>{{ $item->branch?->company?->name ?? '-' }}</div>
                                            <div class="text-muted small">{{ $item->branch?->name }}</div>
                                        @else
                                            {{ $item->branch?->name ?? '-' }}
                                        @endif
                                    </td>
                                @endif
                                @if ($childConfig)
                                    <td class="text-center">
                                        <a href="{{ route($routes.'.'.$child.'.index', ['parent' => $item->id]) }}">{{ $item->{$child.'_count'} }}</a>
                                    </td>
                                @endif
                                <td><span class="badge {{ $item->statusBadge() }}">{{ $item->statusLabel() }}</span></td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex flex-nowrap justify-content-end gap-2">
                                    @includeIf('schedule.'.$resource.'._actions')
                                    @if ($canManage)
                                        @if ($childConfig)
                                            <a href="{{ route($routes.'.'.$child.'.create', ['parent' => $item->id]) }}" class="btn btn-sm rounded-pill btn-outline-success">
                                                <i class="ri-add-line"></i> {{ $childConfig['label'] }}
                                            </a>
                                        @endif
                                        <a href="{{ route($routes.'.'.$resource.'.edit', $item) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-edit-line"></i> Edit</a>
                                        <form action="{{ route($routes.'.'.$resource.'.destroy', $item) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus '.strtolower($config['label']).' '.$item->name.'?') }});">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i> Hapus</button>
                                        </form>
                                    @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 3 + count($columns) + ($showBranch ? 1 : 0) + ($childConfig ? 1 : 0) + (($billing ?? false) ? 1 : 0) }}" class="text-center text-muted py-4">
                                    {{ $filtered ? 'Tidak ada '.strtolower($config['label']).' yang cocok.' : 'Belum ada '.strtolower($config['label']).'. Yuk tambahkan yang pertama.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>

    @if ($billing)
        @push('scripts')
            <script>
                (() => {
                    const boxes = [...document.querySelectorAll('[data-bill]')];
                    const all = document.querySelector('[data-bill-all]');
                    const count = document.querySelector('[data-bill-count]');
                    const update = () => {
                        const checked = boxes.filter(box => box.checked).length;
                        count.textContent = checked;
                        document.querySelector('[data-bill-submit]').disabled = checked === 0;
                        all.checked = checked === boxes.length;
                        all.indeterminate = checked > 0 && checked < boxes.length;
                    };
                    all.addEventListener('change', () => { boxes.forEach(box => box.checked = all.checked); update(); });
                    boxes.forEach(box => box.addEventListener('change', update));
                })();
            </script>
        @endpush
    @endif
@endsection
