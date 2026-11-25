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
                                <select name="channel" class="form-select" required @disabled($app->exists && $app->stores()->exists())>
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
                                <label class="form-label">App Key</label>
                                <input type="text" name="app_key" class="form-control font-monospace" maxlength="100" value="{{ old('app_key', $app->app_key) }}" required autocomplete="off">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">App Secret</label>
                                <input type="password" name="app_secret" class="form-control font-monospace" maxlength="255" {{ $app->exists ? '' : 'required' }} autocomplete="new-password"
                                    placeholder="{{ $app->exists ? 'Kosongkan bila tidak diganti ('.$app->maskedSecret().')' : '' }}">
                                <div class="form-text">Disimpan terenkripsi dan tidak pernah ditampilkan utuh.</div>
                            </div>
                            <div class="col-12 col-md-8">
                                <label class="form-label">Callback URL</label>
                                <input type="url" name="callback_url" class="form-control" maxlength="255" value="{{ old('callback_url', $app->callback_url) }}" placeholder="{{ route('marketplace.lazada.callback') }}">
                                <div class="form-text">Kosongkan untuk memakai alamat bawaan bizbos: <code>{{ route('marketplace.lazada.callback') }}</code>. Isi alamat yang <b>sama persis</b> di Callback URL app pada Lazada ISV Console.</div>
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
                </div>
            </div>
        </div>
    </div>
@endsection
