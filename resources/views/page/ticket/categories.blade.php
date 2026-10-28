@extends('layouts.dashboard')

@section('title', 'Kategori Tiket')
@section('page-section', \App\Support\Apps::pageSection($routes))

@php
    $fmt = fn ($date) => $date?->format('Y-m-d\TH:i');
    $rows = old('categories', $categories->map(fn ($c) => [
        'locked' => $c->sold + $c->reserved > 0,
        'id' => $c->id, 'name' => $c->name, 'description' => $c->description, 'price' => $c->price, 'quota' => $c->quota,
        'max_per_order' => $c->max_per_order, 'sale_starts_at' => $fmt($c->sale_starts_at), 'sale_ends_at' => $fmt($c->sale_ends_at), 'status' => $c->status,
    ])->all());
    $rupiah = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
@endphp

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-categories'])

    @if ($categories->isNotEmpty())
        <div class="card">
            <div class="card-header bg-white border-bottom px-4 py-3">
                <h6 class="mb-1 fw-semibold">Penjualan per Kategori Tiket</h6>
                <div class="text-muted small lh-sm">Klik angka di kolom Terjual untuk melihat siapa saja yang sudah membeli.</div>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kategori Tiket</th>
                                <th class="text-end">Harga</th>
                                <th class="text-center">Dijual</th>
                                <th class="text-center">Terjual</th>
                                <th class="text-center">Menunggu Bayar</th>
                                <th class="text-center">Masih Tersedia</th>
                                <th class="text-end">Total Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td class="fw-semibold">{{ $category->name }} @unless ($category->isActive()) <span class="badge bg-light text-muted">Tidak aktif</span> @endunless</td>
                                    <td class="text-end text-nowrap">{!! $category->price > 0 ? e($rupiah($category->price)) : '<span class="badge bg-success-subtle text-success">Gratis</span>' !!}</td>
                                    <td class="text-center">{{ $category->quota }}</td>
                                    <td class="text-center"><a href="{{ route($routes.'.ticket.categories.sales', [$page, $category]) }}" class="fw-semibold">{{ $category->sold }}</a></td>
                                    <td class="text-center text-warning">{{ $category->reserved }}</td>
                                    <td class="text-center {{ $category->remaining() === 0 ? 'text-danger fw-semibold' : '' }}">{{ $category->remaining() }}</td>
                                    <td class="text-end text-nowrap">{{ $rupiah($category->revenue()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="3" class="text-end">Total</td>
                                <td class="text-center">{{ $categories->sum('sold') }}</td>
                                <td class="text-center">{{ $categories->sum('reserved') }}</td>
                                <td class="text-center">{{ $categories->sum(fn ($c) => $c->remaining()) }}</td>
                                <td class="text-end text-nowrap">{{ $rupiah($categories->sum(fn ($c) => $c->revenue())) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endif


    <form method="POST" action="{{ route($routes.'.ticket.categories.sync', $page) }}" id="ticket-categories-form">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h6 class="mb-1 fw-semibold">Atur Kategori Tiket</h6>
                    <div class="text-muted small lh-sm">Tentukan jenis tiket yang dijual, mis. VIP, Reguler, atau Early Bird. Geser <i class="ri-draggable"></i> untuk mengubah urutan tampil. Kategori yang sudah ada pembelinya tidak bisa dihapus.</div>
                </div>
                @if ($canManage)
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="ri-save-line me-1"></i>Simpan Perubahan</button>
                @endif
            </div>
            <div class="card-body bg-light p-3 p-md-4">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $message) <div>{{ $message }}</div> @endforeach
                    </div>
                @endif
                <div class="alert {{ $freeEvent ? 'alert-success' : 'alert-primary' }} small mb-3">
                    @if ($freeEvent)
                        <strong>Event gratis.</strong> Semua tiket Rp0{{ $freeLimit ? ', dengan total maksimal '.number_format($freeLimit, 0, ',', '.').' tiket untuk semua kategori' : '' }}.
                        @if ($freeLimit) <span data-free-total data-limit="{{ $freeLimit }}"></span> @endif
                    @else
                        <strong>{{ \App\Models\TicketSetting::PAYER_LABELS[$page->ticketFeePayer()] }}.</strong> Biaya layanan dihitung dari harga tiket berbayar setiap pesanan.
                    @endif
                    Kategori yang sudah ada pembelinya tidak bisa diubah nama dan harganya. Setiap perubahan tercatat di Riwayat Perubahan.
                </div>
                <fieldset @disabled(! $canManage)>
                    <div data-sortable id="ticket-rows">
                        @foreach ($rows as $i => $row)
                            @include('page.ticket._category-row')
                        @endforeach
                    </div>
                    <div id="ticket-empty" class="text-center text-muted py-4 {{ count($rows) ? 'd-none' : '' }}">
                        <i class="ri-coupon-3-line fs-2 d-block mb-1"></i>Belum ada kategori tiket. Klik tombol Tambah Kategori Tiket di bawah.
                    </div>
                </fieldset>
                @if ($canManage)
                    <button type="button" class="btn btn-outline-primary w-100 mt-2" id="add-ticket"><i class="ri-add-line"></i> Tambah Kategori Tiket</button>
                    <template id="ticket-template">@include('page.ticket._category-row', ['i' => '__INDEX__', 'row' => []])</template>
                @endif
            </div>
        </div>
    </form>

    @if ($history->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold">Riwayat Perubahan Kategori</h6></div>
            <div class="card-body p-3 p-md-4">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small">
                        <thead class="table-light"><tr><th class="text-nowrap">Waktu</th><th>Kategori</th><th>Perubahan</th><th>Oleh</th></tr></thead>
                        <tbody>
                            @foreach ($history as $change)
                                <tr>
                                    <td class="text-nowrap">{{ $change->created_at->translatedFormat('d M Y H:i') }}</td>
                                    <td>{{ $change->category_name }}</td>
                                    <td>
                                        @foreach ($change->changes as $field => [$from, $to])
                                            <div>{{ \App\Models\TicketCategoryChange::FIELDS[$field] ?? $field }}: <span class="text-muted text-decoration-line-through">{{ $from }}</span> → <strong>{{ $to }}</strong></div>
                                        @endforeach
                                    </td>
                                    <td class="text-nowrap">{{ $change->actor?->name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <script src="{{ asset('be/assets/js/sortable-rows.js') }}"></script>
    <script>
        (function () {
            var rows = document.getElementById('ticket-rows'), template = document.getElementById('ticket-template'), counter = 0;
            function refresh() { document.getElementById('ticket-empty').classList.toggle('d-none', rows.children.length > 0); }
            if (!template) return;
            document.getElementById('add-ticket').addEventListener('click', function () {
                rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, 'n' + (counter++)));
                rows.lastElementChild.querySelector('input[type=text]').focus();
                refresh();
            });
            // Event gratis: tampilkan total kuota semua kategori dibanding batasnya (server tetap mengecek ulang).
            var info = document.querySelector('[data-free-total]');
            function freeTotal() {
                if (!info) return;
                var total = 0;
                rows.querySelectorAll('[name$="[quota]"]').forEach(function (input) { total += Math.max(0, parseInt(input.value, 10) || 0); });
                var limit = Number(info.dataset.limit);
                info.innerHTML = 'Terpakai <strong>' + total.toLocaleString('id-ID') + '</strong> dari ' + limit.toLocaleString('id-ID') + '.' + (total > limit ? ' <span class="text-danger fw-semibold">Melebihi batas, kurangi jumlahnya.</span>' : '');
            }
            rows.addEventListener('input', freeTotal);
            freeTotal();
            rows.addEventListener('click', function (event) {
                if (!event.target.closest('[data-remove]')) return;
                event.target.closest('[data-row]').remove();
                refresh();
            });
        })();

    </script>
@endsection
