@extends('layouts.dashboard')

@section('title', $app->exists ? 'Edit Marketplace App' : 'Tambah Marketplace App')
@section('page-section', 'Superadmin')

@section('content')
    <div class="row">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ $app->exists ? route('superadmin.marketplace-apps.update', $app) : route('superadmin.marketplace-apps.store') }}">
                        @csrf
                        @if ($app->exists) @method('PUT') @endif

                        @if ($errors->any())
                            <div class="alert alert-danger">{{ $errors->first() }}</div>
                        @endif

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Channel</label>
                                <select name="channel" class="form-select" required data-channel @disabled($app->exists && $app->stores()->exists())>
                                    @foreach ($channels as $key => $channel)
                                        <option value="{{ $key }}" @selected(old('channel', $app->channel) === $key)>{{ $channel['label'] }}{{ $channel['enabled'] ? '' : ' (driver segera hadir)' }}</option>
                                    @endforeach
                                </select>
                                @if ($app->exists && $app->stores()->exists())
                                    <input type="hidden" name="channel" value="{{ $app->channel }}">
                                @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Nama app</label>
                                <input type="text" name="name" class="form-control" maxlength="100" value="{{ old('name', $app->name) }}" required placeholder="mis. Lazada bizbos (Testing)">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" data-label-key>{{ \App\Models\MarketplaceApp::credentialLabel(old('channel', $app->channel), 'key') }}</label>
                                <input type="text" name="app_key" class="form-control font-monospace" maxlength="100" value="{{ old('app_key', $app->app_key) }}" required autocomplete="off">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" data-label-secret>{{ \App\Models\MarketplaceApp::credentialLabel(old('channel', $app->channel), 'secret') }}</label>
                                <input type="password" name="app_secret" class="form-control font-monospace" maxlength="255" {{ $app->exists ? '' : 'required' }} autocomplete="new-password"
                                    placeholder="{{ $app->exists ? 'Kosongkan bila tidak diganti ('.$app->maskedSecret().')' : '' }}">
                                <div class="form-text">Disimpan terenkripsi dan tidak pernah ditampilkan utuh.</div>
                            </div>
                            <div class="col-12 col-md-6" data-only-channel="tiktok">
                                <label class="form-label">Service ID</label>
                                <input type="text" name="service_id" class="form-control font-monospace" maxlength="64" value="{{ old('service_id', $app->service_id) }}" autocomplete="off">
                                <div class="form-text">TikTok Shop: dipakai di link otorisasi seller (Partner Center › App & Service › detail app).</div>
                            </div>
                            <div class="col-12 col-md-8">
                                <label class="form-label">Callback URL</label>
                                <input type="url" name="callback_url" class="form-control" maxlength="255" value="{{ old('callback_url', $app->callback_url) }}" data-callback-input>
                                <div class="form-text">
                                    Kosongkan untuk memakai alamat bawaan bizbos: <code data-callback-default>{{ route('marketplace.lazada.callback') }}</code>.
                                    Lazada: isi alamat yang <b>sama persis</b> di Callback URL app (ISV Console). Shopee: isi <b>domain</b>-nya di Redirect URL Domain (Test / Live) pada Shopee Console.
                                    TikTok Shop: isi alamat ini di <b>Redirect URL</b> app pada Partner Center.
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Negara default</label>
                                <select name="region" class="form-select" required>
                                    @foreach ($regions as $code => $label)
                                        <option value="{{ $code }}" @selected(old('region', $app->region) === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Environment</label>
                                <select name="environment" class="form-select" required>
                                    @foreach (\App\Models\MarketplaceApp::ENVIRONMENTS as $code => $label)
                                        <option value="{{ $code }}" @selected(old('environment', $app->environment ?? 'live') === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Shopee: Test Partner ID/Key = Sandbox, Live Partner ID/Key = Live. Lazada selalu Live.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="active" @selected(old('status', $app->status) === 'active')>Aktif</option>
                                    <option value="inactive" @selected(old('status', $app->status) === 'inactive')>Tidak aktif (tidak bisa dipakai sambungan baru)</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input type="hidden" name="is_default" value="0">
                                    <input type="checkbox" name="is_default" value="1" class="form-check-input" id="is_default" @checked(old('is_default', $app->is_default))>
                                    <label class="form-check-label" for="is_default">Jadikan default untuk channel ini</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Catatan</label>
                                <textarea name="notes" class="form-control" rows="2" maxlength="1000" placeholder="mis. akun developer, status Testing/Online, batas 10.000 call/hari">{{ old('notes', $app->notes) }}</textarea>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Simpan</button>
                            <a href="{{ route('superadmin.marketplace-apps.index') }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-body small">
                    <h6>Cara mengisi (Lazada)</h6>
                    <ol class="ps-3 mb-0">
                        <li>Buka <a href="https://isvconsole.lazada.com/apps/console/apps" target="_blank" rel="noopener">Lazada ISV Console</a> › Development › App Management › <b>Manage</b>.</li>
                        <li>Salin <b>App Key</b> dan <b>App Secret</b> (klik View) ke form ini.</li>
                        <li>Pastikan <b>Callback URL</b> di ISV Console sama persis dengan callback di form ini.</li>
                        <li>Untuk upload produk, app butuh izin API Product Management; untuk update stok/harga: Price Stock Management.</li>
                        <li>App status <b>Testing</b>: maks. 10.000 call/hari & token lebih pendek.</li>
                    </ol>

                    <h6 class="mt-3">Cara mengisi (Shopee)</h6>
                    <ol class="ps-3 mb-0">
                        <li>Buka <a href="https://open.shopee.com/console" target="_blank" rel="noopener">Shopee Open Platform Console</a> › App List › pilih app (tipe <b>ERP System</b> untuk melayani banyak seller).</li>
                        <li>Sandbox: salin <b>Test Partner ID</b> & <b>Test Key</b>, pilih Environment = Sandbox. Produksi (setelah Go Live): <b>Live Partner ID</b> & <b>Live Key</b>, Environment = Live.</li>
                        <li>Isi <b>Redirect URL Domain</b> (Test / Live) di Console dengan domain bizbos, mis. <code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code>.</li>
                        <li>Uji coba: buat toko tes di Console › Test Account – Sandbox v2, lalu sambungkan toko tes itu dari tab Marketplace.</li>
                    </ol>

                    <h6 class="mt-3">Cara mengisi (TikTok Shop)</h6>
                    <ol class="ps-3 mb-0">
                        <li>Buka <a href="https://partner.tiktokshop.com" target="_blank" rel="noopener">TikTok Shop Partner Center</a> › App & Service › pilih app.</li>
                        <li>Salin <b>App Key</b>, <b>App Secret</b> dan <b>Service ID</b>; aktifkan scope produk (Product Read/Modify) & logistik.</li>
                        <li>Isi <b>Redirect URL</b> app dengan callback TikTok di form ini. Environment = Live.</li>
                        <li>Uji coba: pakai <b>Seller Center development shop</b> dari Partner Center.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@php
    $callbackMap = [];
    foreach (array_keys(config('marketplace.channels')) as $channelKey) {
        $callbackMap[$channelKey] = \Illuminate\Support\Facades\Route::has('marketplace.'.$channelKey.'.callback') ? route('marketplace.'.$channelKey.'.callback') : '';
    }
@endphp

@push('scripts')
    <script>
        (function () {
            const labels = @json(\App\Models\MarketplaceApp::CREDENTIAL_LABELS);
            const callbacks = @json($callbackMap);
            const select = document.querySelector('[data-channel]');
            const sync = () => {
                const l = labels[select.value] || { key: 'App Key', secret: 'App Secret' };
                document.querySelector('[data-label-key]').textContent = l.key;
                document.querySelector('[data-label-secret]').textContent = l.secret;
                const cb = callbacks[select.value] || '';
                document.querySelector('[data-callback-default]').textContent = cb;
                document.querySelector('[data-callback-input]').placeholder = cb;
                document.querySelectorAll('[data-only-channel]').forEach((el) => el.classList.toggle('d-none', el.dataset.onlyChannel !== select.value));
            };
            select.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
