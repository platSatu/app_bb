@extends('layouts.dashboard')

@section('title', 'Pengaturan AI')
@section('page-section', 'Superadmin')

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-1 fw-semibold">Penyedia AI</h6>
                    <div class="text-muted small lh-sm">User memakai AI sebagai fasilitas bizbos. Hanya satu penyedia yang aktif; API key disimpan terenkripsi dan tidak pernah ditampilkan lagi.</div>
                </div>
                <div class="card-body p-3 p-md-4">
                    @forelse ($providers as $provider)
                        <details class="border rounded-3 p-3 mb-2 {{ $provider->is_active ? 'border-success' : '' }}">
                            <summary class="d-flex align-items-center justify-content-between gap-2 flex-wrap" style="list-style: none; cursor: pointer;">
                                <div>
                                    <span class="fw-semibold">{{ $provider->name }}</span>
                                    @if ($provider->is_active) <span class="badge bg-success-subtle text-success">Aktif dipakai</span> @endif
                                    <div class="small text-muted">{{ $provider->driverLabel() }} · {{ $provider->model }}</div>
                                </div>
                                <div class="d-flex gap-1" onclick="event.stopPropagation()">
                                    <form method="POST" action="{{ route('superadmin.ai.test', $provider) }}">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary"><i class="ri-plug-line"></i> Tes</button></form>
                                    @unless ($provider->is_active)
                                        <form method="POST" action="{{ route('superadmin.ai.activate', $provider) }}">@csrf<button type="submit" class="btn btn-sm btn-outline-success">Aktifkan</button></form>
                                    @endunless
                                    <form method="POST" action="{{ route('superadmin.ai.destroy', $provider) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus penyedia '.$provider->name.'?') }});">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </div>
                            </summary>
                            <form method="POST" action="{{ route('superadmin.ai.update', $provider) }}" class="mt-3">
                                @csrf @method('PUT')
                                @include('superadmin.ai._fields', ['provider' => $provider])
                                <div class="d-flex justify-content-end mt-2"><button type="submit" class="btn btn-sm btn-primary">Simpan</button></div>
                            </form>
                        </details>
                    @empty
                        <div class="text-center text-muted py-3">Belum ada penyedia. Tambahkan di bawah, lalu klik Tes.</div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold"><i class="ri-add-line"></i> Tambah Penyedia</h6></div>
                <div class="card-body p-3 p-md-4">
                    @if ($errors->any())
                        <div class="alert alert-danger">@foreach ($errors->all() as $message) <div>{{ $message }}</div> @endforeach</div>
                    @endif
                    <form method="POST" action="{{ route('superadmin.ai.store') }}">
                        @csrf
                        @include('superadmin.ai._fields', ['provider' => new \App\Models\AiProvider(['driver' => 'openai'])])
                        <div class="d-flex justify-content-end mt-2"><button type="submit" class="btn btn-primary">Tambah</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-1 fw-semibold">Kuota Harian</h6>
                    <div class="text-muted small lh-sm">Batas "Buat dengan AI" per user per hari. Tim platform tidak dibatasi. 0 = AI dimatikan untuk user.</div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form method="POST" action="{{ route('superadmin.ai.quota') }}">
                        @csrf @method('PUT')
                        <div class="input-group mb-2">
                            <input type="number" name="daily_quota" min="0" max="1000" value="{{ old('daily_quota', $setting->daily_quota) }}" class="form-control">
                            <span class="input-group-text">kali / hari</span>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
