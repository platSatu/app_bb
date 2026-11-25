@extends('layouts.dashboard')

@section('title', 'Marketplace Apps')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <p class="text-muted mb-1">Kredensial app developer marketplace (Lazada ISV Console, nanti TikTok Shop & Shopee). Owner memakai app ini saat menyambungkan toko di tab Marketplace pada Catalog Online.</p>
                    <p class="small text-muted mb-0">Boleh lebih dari satu app per channel (akun developer berbeda). App <b>default</b> dipakai untuk sambungan baru; toko yang sudah tersambung tetap memakai app saat ia diotorisasi.</p>
                </div>
                <a href="{{ route('superadmin.marketplace-apps.create') }}" class="btn btn-primary text-nowrap"><i class="ri-add-line"></i> Tambah App</a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 900px">
                    <thead class="table-light">
                        <tr>
                            <th>Channel</th>
                            <th>Nama</th>
                            <th>App Key</th>
                            <th>App Secret</th>
                            <th>Callback URL</th>
                            <th class="text-center">Toko</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($apps as $app)
                            <tr>
                                <td>
                                    <i class="{{ $channels[$app->channel]['icon'] ?? 'ri-store-line' }}"></i> {{ $app->channelLabel() }}
                                    <div class="small text-muted">{{ \App\Models\MarketplaceApp::REGIONS[$app->region] ?? $app->region }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $app->name }}</div>
                                    @if ($app->is_default) <span class="badge bg-primary-subtle text-primary">Default</span> @endif
                                </td>
                                <td class="font-monospace">{{ $app->app_key }}</td>
                                <td class="font-monospace small">{{ $app->maskedSecret() }}</td>
                                <td class="small" style="max-width: 260px">
                                    <div class="d-flex align-items-center gap-1">
                                        <code class="text-truncate" title="{{ $app->callbackUrl() }}">{{ $app->callbackUrl() }}</code>
                                        <button type="button" class="btn btn-sm btn-link p-0" title="Salin" onclick="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($app->callbackUrl()) }}); this.innerHTML = '✓'"><i class="ri-file-copy-line"></i></button>
                                    </div>
                                </td>
                                <td class="text-center">{{ $app->stores_count }}</td>
                                <td><span class="badge {{ $app->statusBadge() }}">{{ $app->statusLabel() }}</span></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('superadmin.marketplace-apps.edit', $app) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-edit-line"></i> Edit</a>
                                    <form method="POST" action="{{ route('superadmin.marketplace-apps.destroy', $app) }}" class="d-inline"
                                        onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus app '.$app->name.'?') }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm rounded-pill btn-outline-danger" @disabled($app->stores_count > 0) title="{{ $app->stores_count > 0 ? 'Masih dipakai toko' : 'Hapus' }}"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada app. Klik "Tambah App", lalu isi App Key & App Secret dari Lazada ISV Console (Development › App Management › Manage).</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
