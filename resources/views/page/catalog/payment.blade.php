@extends('layouts.dashboard')

@section('title', 'Pembayaran Katalog')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@section('content')
    @include('page.category._tabs', ['active' => 'catalog-payment'])

    @php
        $banks = old('banks', $page->setting('catalog.payment.banks', []));
        $rows = array_pad($banks, max(count($banks), 1), ['bank' => '', 'number' => '', 'holder' => '']);
    @endphp

    <form method="POST" action="{{ route($routes.'.catalog.payment.update', $page) }}">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h6 class="mb-1 fw-semibold">Pembayaran</h6>
                    <div class="text-muted small lh-sm">Pilih cara pembeli membayar pesanan. Kalau semua dimatikan, pesanan dikonfirmasi langsung oleh Anda seperti sebelumnya.</div>
                </div>
                @if ($canManage)
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="ri-save-line me-1"></i>Simpan</button>
                @endif
            </div>
            <div class="card-body p-3 p-md-4">
                <fieldset @disabled(! $canManage)>
                    {{-- Bayar online lewat bizbos --}}
                    <div class="border rounded-3 p-3 mb-3">
                        <div class="form-check form-switch mb-1">
                            <input type="hidden" name="online" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="online" name="online" value="1" @checked(old('online', $page->setting('catalog.payment.online')))>
                            <label class="form-check-label fw-semibold" for="online">Aktifkan pembayaran di bizbos</label>
                        </div>
                        <div class="small text-muted">Pembeli bayar lewat VA, QRIS, atau e-wallet. Lunas otomatis, stok terkunci selama menunggu bayar, dan uangnya masuk ke saldo Anda seperti penjualan tiket.</div>
                        @unless ($gatewayReady)
                            <div class="small text-warning mt-1"><i class="ri-information-line"></i> Payment gateway bizbos belum siap, jadi opsi ini belum tampil ke pembeli.</div>
                        @endunless
                    </div>

                    {{-- Transfer bank ke rekening owner --}}
                    <div class="border rounded-3 p-3">
                        <div class="form-check form-switch mb-1">
                            <input type="hidden" name="transfer" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="transfer" name="transfer" value="1" data-toggle-section="#bank-section" @checked(old('transfer', $page->setting('catalog.payment.transfer')))>
                            <label class="form-check-label fw-semibold" for="transfer">Aktifkan transfer bank</label>
                        </div>
                        <div class="small text-muted">Pembeli transfer langsung ke rekening Anda. Setelah dana masuk, ubah status pesanan menjadi <b>Lunas</b> di tab Pesanan.</div>
                        @error('banks') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                        <div id="bank-section" class="mt-3">
                            <div data-bank-list>
                                @foreach ($rows as $i => $bank)
                                    <div class="row g-2 mb-2" data-bank-row>
                                        <div class="col-md-3">
                                            <input type="text" name="banks[{{ $i }}][bank]" maxlength="50" class="form-control @error("banks.$i.bank") is-invalid @enderror" placeholder="Bank, mis. BCA" value="{{ $bank['bank'] ?? '' }}">
                                        </div>
                                        <div class="col-md-4">
                                            <input type="text" name="banks[{{ $i }}][number]" maxlength="30" inputmode="numeric" class="form-control @error("banks.$i.number") is-invalid @enderror" placeholder="Nomor rekening" value="{{ $bank['number'] ?? '' }}">
                                        </div>
                                        <div class="col-md-4">
                                            <input type="text" name="banks[{{ $i }}][holder]" maxlength="100" class="form-control @error("banks.$i.holder") is-invalid @enderror" placeholder="Atas nama" value="{{ $bank['holder'] ?? '' }}">
                                        </div>
                                        <div class="col-md-1 d-grid">
                                            <button type="button" class="btn btn-outline-danger" data-bank-remove title="Hapus rekening"><i class="ri-delete-bin-line"></i></button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @foreach (['bank', 'number', 'holder'] as $field)
                                @if ($errors->has("banks.*.$field")) <div class="text-danger small">{{ $errors->first("banks.*.$field") }}</div> @endif
                            @endforeach
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bank-add><i class="ri-add-line me-1"></i>Tambah rekening</button>
                            <div class="form-text">Maksimal {{ \App\Services\Catalog\CatalogOrderService::MAX_BANKS }} rekening.</div>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            var toggle = document.getElementById('transfer'), section = document.getElementById('bank-section');
            var sync = function () { section.hidden = !toggle.checked; };
            toggle.addEventListener('change', sync);
            sync();

            var list = document.querySelector('[data-bank-list]'), max = {{ \App\Services\Catalog\CatalogOrderService::MAX_BANKS }};
            // Nama input diurutkan ulang supaya index-nya selalu 0..n.
            var reindex = function () {
                list.querySelectorAll('[data-bank-row]').forEach(function (row, i) {
                    row.querySelectorAll('input').forEach(function (input) { input.name = input.name.replace(/banks\[\d+\]/, 'banks[' + i + ']'); });
                });
            };

            document.querySelector('[data-bank-add]').addEventListener('click', function () {
                var rows = list.querySelectorAll('[data-bank-row]');
                if (rows.length >= max) return;
                var clone = rows[rows.length - 1].cloneNode(true);
                clone.querySelectorAll('input').forEach(function (input) { input.value = ''; input.classList.remove('is-invalid'); });
                list.appendChild(clone);
                reindex();
            });

            list.addEventListener('click', function (event) {
                var button = event.target.closest('[data-bank-remove]');
                if (!button) return;
                var rows = list.querySelectorAll('[data-bank-row]');
                var row = button.closest('[data-bank-row]');
                if (rows.length > 1) row.remove(); else row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
                reindex();
            });
        })();
    </script>
@endpush
