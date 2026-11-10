@extends('layouts.dashboard')

@section('title', 'Sett & Lap')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.deposit._tabs', ['active' => 'setting'])

    @php($canManage = auth()->user()->canAccess('superadmin.payment-gateway', \App\Support\Menu::ABILITY_MANAGE))

    <div class="card">
        <div class="card-body">
            <h5 class="mb-1">Duitku</h5>
            <p class="text-muted mb-4">Kredensial Sandbox dan Production disimpan terpisah, tinggal pilih mode yang aktif.</p>

            <form method="POST" action="{{ route('superadmin.payment-gateway.setting.update') }}">
                @csrf
                @method('PUT')

                <fieldset @disabled(! $canManage)>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="mode" class="form-label">Mode Aktif</label>
                        <select id="mode" name="mode" class="form-select @error('mode') is-invalid @enderror">
                            <option value="sandbox" @selected(old('mode', $setting->mode) === 'sandbox')>Sandbox (uji coba)</option>
                            <option value="production" @selected(old('mode', $setting->mode) === 'production')>Production</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="expiry_minutes" class="form-label">Batas Waktu Bayar (menit)</label>
                        <input type="number" id="expiry_minutes" name="expiry_minutes" min="5" max="1440"
                            class="form-control @error('expiry_minutes') is-invalid @enderror" value="{{ old('expiry_minutes', $setting->expiry_minutes) }}">
                        @error('expiry_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                @checked(old('is_active', $setting->is_active))>
                            <label class="form-check-label" for="is_active">Top up aktif</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="min_deposit" class="form-label">Minimal Top Up (Rp)</label>
                        <input type="number" id="min_deposit" name="min_deposit" min="1000"
                            class="form-control @error('min_deposit') is-invalid @enderror" value="{{ old('min_deposit', $setting->min_deposit) }}">
                        @error('min_deposit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="max_deposit" class="form-label">Maksimal Top Up (Rp)</label>
                        <input type="number" id="max_deposit" name="max_deposit" min="1000"
                            class="form-control @error('max_deposit') is-invalid @enderror" value="{{ old('max_deposit', $setting->max_deposit) }}">
                        @error('max_deposit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row g-3">
                    @foreach (['sandbox' => 'Sandbox', 'production' => 'Production'] as $mode => $label)
                        <div class="col-md-6">
                            <div class="card border mb-0 h-100">
                                <div class="card-body">
                                    <h6 class="mb-3">{{ $label }}</h6>
                                    <div class="mb-3">
                                        <label for="{{ $mode }}_merchant_code" class="form-label">Merchant Code</label>
                                        <input type="text" id="{{ $mode }}_merchant_code" name="{{ $mode }}_merchant_code" class="form-control"
                                            value="{{ old($mode.'_merchant_code', $setting->{$mode.'_merchant_code'}) }}" placeholder="mis. DS12345">
                                    </div>
                                    <div>
                                        <label for="{{ $mode }}_api_key" class="form-label">API Key</label>
                                        <input type="password" id="{{ $mode }}_api_key" name="{{ $mode }}_api_key" class="form-control" autocomplete="new-password"
                                            placeholder="{{ $setting->{$mode.'_api_key'} ? 'Sudah tersimpan, isi untuk mengganti' : 'Belum diisi' }}">
                                        <div class="form-text">Disimpan terenkripsi. Kosongkan kalau tidak ingin mengganti.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="card border mt-4 mb-0">
                    <div class="card-body">
                        <h6 class="mb-1">Masa Settlement (uang bisa ditarik)</h6>
                        <p class="small text-muted mb-3">Uang dari pembayaran langsung masuk saldo owner, tapi baru bisa ditarik / dikirim lewat disbursement setelah settlement Duitku. Dihitung dalam hari kerja (Sabtu, Minggu & tanggal libur dilewati), mulai pukul 12.00. Sesuaikan dengan aturan di akun Duitku. Perubahan berlaku untuk pembayaran berikutnya.</p>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="settlement_default_days" class="form-label">Default (hari kerja)</label>
                                <input type="number" id="settlement_default_days" name="settlement_default_days" min="0" max="14"
                                    class="form-control @error('settlement_default_days') is-invalid @enderror" value="{{ old('settlement_default_days', $setting->settlement_default_days ?? 2) }}">
                                <div class="form-text">Untuk metode yang tidak ada di daftar.</div>
                                @error('settlement_default_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-5">
                                <label for="settlement_methods" class="form-label">Per metode bayar</label>
                                <textarea id="settlement_methods" name="settlement_methods" rows="8" class="form-control font-monospace small @error('settlement_methods') is-invalid @enderror"
                                    placeholder="SP | ShopeePay QRIS | 2">{{ old('settlement_methods', collect($setting->settlementMethods())->map(fn ($m, $code) => $code.' | '.$m['name'].' | '.($m['days'] ?? ''))->implode("\n")) }}</textarea>
                                <div class="form-text">Satu baris: <code>KODE | Nama | hari kerja</code>. Hari kosong = ikut default. Kode sesuai <code>paymentCode</code> Duitku.</div>
                                @error('settlement_methods') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="settlement_holidays" class="form-label">Tanggal libur</label>
                                <textarea id="settlement_holidays" name="settlement_holidays" rows="8" class="form-control font-monospace small @error('settlement_holidays') is-invalid @enderror"
                                    placeholder="2026-12-25">{{ old('settlement_holidays', implode("\n", $setting->settlement_holidays ?? [])) }}</textarea>
                                <div class="form-text">Satu tanggal per baris (YYYY-MM-DD).</div>
                                @error('settlement_holidays') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border mt-4 mb-0">
                    <div class="card-body">
                        <h6 class="mb-1">Biaya Pembayaran Default · Potongan QRIS</h6>
                        <p class="small text-muted mb-3">Berlaku untuk <b>semua aplikasi</b> yang menerima pembayaran Duitku (invoice, API, Google Form, tiket/pendaftaran, toko online, top up) dan semua pelanggan yang tidak punya biaya khusus di bawah. Berlaku untuk pembayaran berikutnya.</p>
                        <ul class="small text-muted mb-3 ps-3">
                            <li><b>Metode yang fee-nya ditanggung bizbos</b> (di bawah, harus <b>sama dengan pengaturan di dashboard Duitku</b>; saat ini QRIS): Duitku memotong fee dari uang masuk, jadi <b>fee asli Duitku</b> transaksi itu (diambil otomatis saat pembayaran dikonfirmasi) dipotong dari saldo pelanggan.</li>
                            <li><b>Metode lain</b> (VA, e-wallet, dll.): fee Duitku dibayar pembeli di atas tagihan, uang masuk utuh. Yang dipotong hanya biaya per pelanggan di bawah (kalau ada).</li>
                            <li>Potongan (%) = <b>potongan minimal</b> metode di atas untuk <b>semua pelanggan</b> (mis. 0,8 = 0,7 Duitku + 0,1 bizbos). Kalau fee asli Duitku atau biaya per pelanggan lebih besar, yang lebih besar yang dipotong. Isi 0 = ikut fee asli Duitku saja. QRIS sampai Rp 100.000 ikut Duitku: kalau Duitku tidak memotong fee (MDR 0% aturan BI), pelanggan juga tidak dipotong.</li>
                            <li>Kode metode baru yang belum dikenal ikut dianggap ditanggung bizbos (aman).</li>
                        </ul>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="qris_fee_percent" class="form-label">Potongan (%)</label>
                                <input type="number" id="qris_fee_percent" name="qris_fee_percent" min="0" max="10" step="0.01"
                                    class="form-control @error('qris_fee_percent') is-invalid @enderror" value="{{ old('qris_fee_percent', $setting->qrisFeePercent()) }}">
                                <div class="form-text">Contoh 0,8 = minimal Rp 800 per Rp 100.000. Isi 0 = ikut fee asli Duitku.</div>
                                @error('qris_fee_percent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-5">
                                <label for="qris_fee_methods" class="form-label">Metode yang fee-nya ditanggung bizbos</label>
                                <input type="text" id="qris_fee_methods" name="qris_fee_methods" placeholder="SP, NQ, SQ"
                                    class="form-control font-monospace @error('qris_fee_methods') is-invalid @enderror" value="{{ old('qris_fee_methods', implode(', ', $setting->qrisMethods())) }}">
                                <div class="form-text">Kode metode, pisahkan dengan koma, sama dengan dashboard Duitku. QRIS: <code>SP</code> ShopeePay, <code>NQ</code> Nobu, <code>SQ</code> Nusapay. Kalau nanti fee VA BCA ditanggung bizbos lagi, tambahkan <code>BC</code>.</div>
                                @error('qris_fee_methods') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-4 mb-0">
                    URL callback untuk didaftarkan di dashboard Duitku:
                    <code>{{ route('payment.duitku.callback') }}</code>
                </div>

                @if ($canManage)
                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                @endif
                </fieldset>
            </form>
        </div>
    </div>

    {{-- Biaya khusus per pelanggan: menimpa default di atas, untuk semua aplikasi pembayaran pelanggan itu. --}}
    <div class="card" id="biaya-pelanggan">
        <div class="card-body">
            <h5 class="mb-1">Biaya per Pelanggan</h5>
            <p class="text-muted small mb-3">
                Menimpa biaya default untuk satu pelanggan, di semua aplikasinya. Urutan: aturan metode yang dibayar → aturan "Semua metode" pelanggan itu → biaya default di atas.
            </p>

            @if ($canManage)
                <form method="POST" action="{{ route('superadmin.payment-gateway.fee-rules.store') }}" class="mb-4">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6 col-xl-4">
                            <label for="fee_user_id" class="form-label">Pelanggan</label>
                            <select id="fee_user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                                <option value="">Pilih pelanggan…</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected(old('user_id') === $customer->id)>{{ $customer->company?->name ?: $customer->name }} — {{ $customer->email }}</option>
                                @endforeach
                            </select>
                            @error('user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="fee_payment_method" class="form-label">Metode</label>
                            <select id="fee_payment_method" name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                                <option value="">Semua metode</option>
                                @foreach ($setting->settlementMethods() as $code => $method)
                                    <option value="{{ $code }}" @selected(old('payment_method') === $code)>{{ $method['name'] }} ({{ $code }})</option>
                                @endforeach
                            </select>
                            @error('payment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6 col-xl-2">
                            <label for="fee_type" class="form-label">Jenis</label>
                            <select id="fee_type" name="fee_type" class="form-select" required>
                                @foreach (\App\Models\PaymentFeeRule::TYPE_LABELS as $value => $label)
                                    <option value="{{ $value }}" @selected(old('fee_type', 'percent') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <label for="fee_value" class="form-label">Nilai</label>
                            <input type="number" id="fee_value" name="fee_value" min="0" step="0.01" inputmode="decimal" placeholder="0"
                                class="form-control @error('fee_value') is-invalid @enderror" value="{{ old('fee_value') }}" required>
                            @error('fee_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top pt-3 mt-3">
                        <div class="form-text m-0">Pelanggan + metode yang sama akan diperbarui (tidak dobel).</div>
                        <button type="submit" class="btn btn-primary px-4"><i class="ri-save-line me-1"></i>Simpan Biaya</button>
                    </div>
                </form>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-nowrap">
                        <tr><th>Pelanggan</th><th>Metode</th><th>Biaya</th><th>Diubah</th>@if ($canManage)<th class="text-end">Aksi</th>@endif</tr>
                    </thead>
                    <tbody class="small">
                        @forelse ($feeRules as $rule)
                            <tr>
                                <td>
                                    {{ $rule->user?->company?->name ?: $rule->user?->name }}
                                    <div class="text-muted">{{ $rule->user?->email }}</div>
                                </td>
                                <td>{{ $rule->payment_method ? (($setting->settlementMethods()[$rule->payment_method]['name'] ?? $rule->payment_method).' ('.$rule->payment_method.')') : 'Semua metode' }}</td>
                                <td class="fw-semibold">{{ $rule->valueLabel() }}</td>
                                <td class="text-muted text-nowrap">{{ $rule->updated_at?->translatedFormat('d M Y H:i') }}</td>
                                @if ($canManage)
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('superadmin.payment-gateway.fee-rules.destroy', $rule) }}" onsubmit="return confirm('Hapus biaya khusus ini? Pelanggan kembali ke biaya default.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada biaya khusus. Semua pelanggan memakai biaya default.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
