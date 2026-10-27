@extends('layouts.dashboard')

@section('title', 'Simulasi Pendapatan Event')
@section('page-section', 'Pages')

@php
    $percentLabel = rtrim(rtrim(number_format((float) $percent, 2, ',', '.'), '0'), ',').'%';
@endphp

@section('content')
    <div class="mt-2 mb-4">
        <h4 class="mb-1">Simulasi Pendapatan Event</h4>
        <p class="text-muted mb-0">Sebelum membuat page tiket, coba hitung dulu berapa yang akan Anda terima. Isi harga dan perkiraan jumlah tiket, angkanya langsung muncul di sebelah kanan.</p>
    </div>

    <form method="GET" action="{{ route('pages.create') }}" id="fee-sim" data-bps="{{ (int) round((float) $percent * 100) }}" data-free-limit="{{ $freeAllowance }}">
        <input type="hidden" name="type" value="{{ $type->id }}">

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                            <div class="d-flex gap-3">
                                <span class="avatar-sm rounded-3 bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px"><i class="ri-coupon-3-line fs-4"></i></span>
                                <div>
                                    <h6 class="mb-0">Kategori Tiket</h6>
                                    <div class="small text-muted">Contoh: VIP, Reguler, Early Bird. Isi 0 untuk tiket gratis.</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-add><i class="ri-add-line"></i> Tambah</button>
                        </div>

                        <div data-rows></div>

                        <template data-row-template>
                            <div class="bg-light rounded-3 p-3 mb-2" data-row>
                                <div class="d-flex gap-2 mb-2">
                                    <input type="text" class="form-control" placeholder="Nama kategori, mis. Reguler" value="Reguler" aria-label="Nama kategori">
                                    <button type="button" class="btn btn-outline-danger" data-remove title="Hapus kategori"><i class="ri-delete-bin-line"></i></button>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small mb-1">Harga tiket (Rp)</label>
                                        <input type="number" class="form-control" min="0" step="1000" value="100000" data-price>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-1">Perkiraan terjual</label>
                                        <input type="number" class="form-control" min="0" value="100" data-qty>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <hr class="my-4">

                        <h6 class="mb-1">Pilih jenis event</h6>
                        <p class="small text-muted mb-3">Bisa diubah lagi di Informasi Page selama belum ada pesanan.</p>
                        @foreach ([
                            \App\Models\TicketSetting::PAYER_ORGANIZER => ['Berbayar, biaya ditanggung saya', "Harga di halaman tiket tetap. Biaya layanan {$percentLabel} dipotong dari hasil penjualan."],
                            \App\Models\TicketSetting::PAYER_BUYER => ['Berbayar, biaya ditanggung pembeli', "Biaya layanan {$percentLabel} ditambahkan saat pembeli checkout. Anda menerima harga tiket utuh."],
                            \App\Models\TicketSetting::PAYER_FREE => ['Event gratis', 'Semua tiket Rp0, tanpa biaya apa pun. Total maksimal '.number_format($freeAllowance, 0, ',', '.').' tiket untuk semua kategori.'],
                        ] as $value => [$label, $hint])
                            <label class="d-flex gap-3 border rounded-3 p-3 mb-2" style="cursor:pointer">
                                <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="fee_payer" value="{{ $value }}" @checked($payer === $value)>
                                <span>
                                    <span class="fw-semibold d-block">{{ $label }}</span>
                                    <span class="small text-muted">{{ $hint }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex gap-3 mb-3">
                            <span class="rounded-3 bg-success-subtle text-success d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px"><i class="ri-money-dollar-circle-line fs-4"></i></span>
                            <div>
                                <h6 class="mb-0">Rincian Pendapatan</h6>
                                <div class="small text-muted">Ringkasan penjualan, biaya, dan yang Anda terima.</div>
                            </div>
                        </div>

                        <div class="alert alert-primary small mb-3">
                            <div class="fw-semibold mb-1"><i class="ri-information-line"></i> Cara kerja biaya layanan</div>
                            Biaya layanan bizbos <strong>{{ $percentLabel }}</strong> dari harga setiap tiket berbayar yang terjual, dipotong dari uang yang masuk atau ditambahkan ke pembeli. Event gratis tidak dikenai biaya.
                        </div>

                        <div class="bg-light rounded-3 p-3 mb-2">
                            <div class="small text-muted">Total penjualan tiket</div>
                            <div class="fs-4 fw-semibold" data-out="sales">Rp 0</div>
                        </div>
                        <div class="bg-light rounded-3 p-3 mb-2">
                            <div class="d-flex justify-content-between small text-muted"><span>Biaya layanan ({{ $percentLabel }})</span><span data-out="payer"></span></div>
                            <div class="fs-4 fw-semibold text-danger" data-out="fee">Rp 0</div>
                        </div>
                        <div class="alert alert-success small mb-2" data-free-only data-out="free-note"></div>
                        <div class="bg-light rounded-3 p-3 mb-2" data-buyer-only>
                            <div class="small text-muted">Total yang dibayar semua pembeli</div>
                            <div class="fs-5 fw-semibold" data-out="paid">Rp 0</div>
                        </div>
                        <div class="rounded-3 p-3 bg-success-subtle">
                            <div class="small text-success">Total yang Anda terima</div>
                            <div class="fs-3 fw-bold text-success" data-out="net">Rp 0</div>
                            <div class="small text-success">Masuk ke Saldo Anda otomatis setiap tiket lunas.</div>
                        </div>
                        <div class="small text-muted mt-2">Ini estimasi. Biaya dihitung per pesanan dan dibulatkan ke atas sampai rupiah, jadi angka sebenarnya bisa selisih beberapa rupiah.</div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-body p-4">
                        <h6 class="mb-3">Yang perlu Anda ketahui</h6>
                        <ul class="small text-muted mb-0 ps-3" style="line-height:1.8">
                            <li>Biaya layanan {{ $percentLabel }} dihitung dari harga tiket berbayar di setiap pesanan.</li>
                            <li><strong>Event gratis:</strong> semua tiket Rp0 dan tidak ada biaya apa pun. Total jumlah tiket dari semua kategori maksimal {{ number_format($freeAllowance, 0, ',', '.') }}. Butuh lebih banyak? Pilih event berbayar.</li>
                            <li>Setelah ada pembeli, <strong>nama dan harga</strong> kategori tiket dikunci. Kolom lain masih boleh diubah dan setiap perubahan tercatat di riwayat.</li>
                            <li><strong>Ditanggung Anda:</strong> pembeli membayar sesuai harga tiket. Biaya layanan dipotong otomatis sebelum hasil penjualan masuk ke Saldo Anda.</li>
                            <li><strong>Ditanggung pembeli:</strong> di halaman pembayaran muncul baris <em>Biaya layanan</em> sehingga pembeli membayar harga tiket + biaya layanan. Anda menerima harga tiket utuh.</li>
                            <li>Biaya metode pembayaran (Virtual Account, e-wallet, dan lainnya) mengikuti metode yang dipilih pembeli dan tidak mengurangi pendapatan Anda.</li>
                            <li>Hasil penjualan masuk ke Saldo setiap kali tiket lunas, dan bisa ditarik ke rekening setelah dana selesai diproses bank (lihat menu Tarik Saldo).</li>
                            <li>Persen biaya yang berlaku dikunci di setiap pesanan. Kalau suatu saat tarifnya berubah, pesanan yang sudah ada tidak ikut berubah.</li>
                            <li>Jenis event bisa diganti selama belum ada pesanan. Setelah itu dikunci supaya semua pembeli mendapat aturan yang sama.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between gap-2 mt-5 mb-5 pt-2 pb-4">
            <a href="{{ route('pages.index') }}" class="btn btn-outline-secondary">Batal</a>
            <button type="submit" class="btn btn-primary px-4">Saya Mengerti, Lanjut Buat Page Tiket <i class="ri-arrow-right-line"></i></button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            var form = document.getElementById('fee-sim');
            var rows = form.querySelector('[data-rows]');
            var template = form.querySelector('[data-row-template]');
            var bps = Number(form.dataset.bps) || 0;
            var money = function (n) { return 'Rp ' + n.toLocaleString('id-ID'); };
            var out = function (key, text) { form.querySelector('[data-out=' + key + ']').textContent = text; };

            // Sama persis dengan TicketSetting::feeFor(): basis poin, dibulatkan ke atas.
            var feeFor = function (subtotal) { return subtotal > 0 && bps > 0 ? Math.floor((subtotal * bps + 9999) / 10000) : 0; };

            function update() {
                var mode = (form.querySelector('[name=fee_payer]:checked') || {}).value, free = mode === 'free', buyer = mode === 'buyer';
                var sales = 0, qtyTotal = 0, limit = Number(form.dataset.freeLimit) || 0;
                rows.querySelectorAll('[data-row]').forEach(function (row) {
                    var priceInput = row.querySelector('[data-price]');
                    // Event gratis: harga dikunci Rp0 (sama seperti di menu Kategori Tiket).
                    if (free) priceInput.value = 0;
                    priceInput.readOnly = free;
                    var price = Math.max(0, parseInt(priceInput.value, 10) || 0);
                    var qty = Math.max(0, parseInt(row.querySelector('[data-qty]').value, 10) || 0);
                    sales += price * qty;
                    qtyTotal += qty;
                });
                form.querySelector('[data-free-only]').classList.toggle('d-none', !free);
                form.querySelector('[data-free-only]').innerHTML = 'Total ' + qtyTotal.toLocaleString('id-ID') + ' dari maksimal ' + limit.toLocaleString('id-ID') + ' tiket gratis.'
                    + (qtyTotal > limit ? ' <strong class="text-danger">Melebihi batas.</strong> Kurangi jumlahnya atau pilih event berbayar.' : ' Tidak ada biaya apa pun.');
                var fee = feeFor(sales);
                out('sales', money(sales));
                out('fee', (buyer ? '+' : '−') + money(fee));
                out('payer', free ? 'Event gratis' : (buyer ? 'Ditanggung pembeli' : 'Ditanggung Anda'));
                out('paid', money(sales + fee));
                out('net', money(buyer ? sales : sales - fee));
                form.querySelector('[data-buyer-only]').classList.toggle('d-none', !buyer);
            }

            function addRow() {
                rows.appendChild(template.content.cloneNode(true));
                update();
            }

            form.querySelector('[data-add]').addEventListener('click', addRow);
            rows.addEventListener('click', function (event) {
                var remove = event.target.closest('[data-remove]');
                if (!remove) return;
                if (rows.querySelectorAll('[data-row]').length > 1) remove.closest('[data-row]').remove();
                update();
            });
            form.addEventListener('input', update);
            form.addEventListener('change', update);
            addRow();
        })();
    </script>
@endpush
