@extends('layouts.dashboard')

@section('title', 'AI Bot · '.$device->name)
@section('page-section', 'WhatsApp')

@section('content')
    <div class="mb-3">
        <a href="{{ route('whatsapp.ai-bots.index', ['branch' => $device->branch_id]) }}" class="small"><i class="ri-arrow-left-line"></i> Kembali ke daftar AI Bot</a>
    </div>

    @if ($answer = session('ai_test'))
        <div class="alert alert-info"><div class="fw-semibold mb-1"><i class="ri-robot-2-line"></i> Contoh jawaban AI</div><div style="white-space: pre-wrap">{{ $answer }}</div></div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('whatsapp.ai-bots.update', $device) }}" class="card mb-0">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Nama bot</label>
                            <input type="text" name="name" maxlength="100" required class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $bot->name ?? 'Asisten '.$device->name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Penyedia AI</label>
                            <select name="driver" class="form-select" data-driver>
                                @foreach (\App\Models\WaAiBot::DRIVERS as $key => [$label, , $model])
                                    <option value="{{ $key }}" data-model="{{ $model }}" @selected(old('driver', $bot->driver) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Model</label>
                            <input type="text" name="model" maxlength="100" required class="form-control @error('model') is-invalid @enderror" value="{{ old('model', $bot->model) }}" data-model>
                            @error('model') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">API key</label>
                            <input type="password" name="api_key" maxlength="300" autocomplete="new-password" class="form-control @error('api_key') is-invalid @enderror" placeholder="{{ $bot->exists ? $bot->maskedKey().' (kosongkan bila tidak diganti)' : 'Tempel API key dari penyedia AI' }}">
                            @error('api_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <label class="form-label mt-3">Peran & gaya bahasa</label>
                    <textarea name="system_prompt" rows="3" maxlength="3000" class="form-control" placeholder="Anda adalah customer service Toko Melati yang ramah, memakai bahasa santai dan sopan.">{{ old('system_prompt', $bot->system_prompt) }}</textarea>

                    <label class="form-label mt-3">Informasi bisnis</label>
                    <textarea name="knowledge" rows="10" maxlength="30000" class="form-control" placeholder="Jam buka, alamat, daftar layanan & harga, cara pesan, kebijakan pengembalian, dll.">{{ old('knowledge', $bot->knowledge) }}</textarea>
                    <div class="form-text">AI hanya menjawab berdasarkan informasi ini. Bila tidak tahu atau pelanggan minta admin, bot meneruskan ke admin lalu diam.</div>

                    <div class="row g-2 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Panjang jawaban maks (token)</label>
                            <input type="number" name="max_tokens" min="50" max="2000" required class="form-control" value="{{ old('max_tokens', $bot->max_tokens) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bot diam setelah diteruskan ke admin (menit)</label>
                            <input type="number" name="handover_minutes" min="5" max="10080" required class="form-control" value="{{ old('handover_minutes', $bot->handover_minutes) }}">
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="bot-active" @checked(old('is_active', $bot->is_active))>
                        <label class="form-check-label" for="bot-active">Aktif menjawab chat</label>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            @if ($bot->exists)
                <div class="card">
                    <div class="card-body">
                        <h6>Tes API key</h6>
                        <p class="small text-muted">Kirim satu pesan uji ke penyedia AI untuk memastikan API key & model benar.</p>
                        <form method="POST" action="{{ route('whatsapp.ai-bots.test', $device) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary"><i class="ri-flask-line"></i> Tes Sekarang</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-2">Aktivitas Terakhir</h6>
                        @forelse ($logs as $log)
                            <div class="d-flex justify-content-between small py-1 border-bottom">
                                <span class="{{ $log->status === 'ok' ? 'text-success' : 'text-danger' }}" title="{{ $log->error }}">{{ ['ok' => 'Dijawab', 'failed' => 'Gagal', 'limited' => 'Dibatasi'][$log->status] ?? $log->status }}{{ $log->error ? ': '.\Illuminate\Support\Str::limit($log->error, 40) : '' }}</span>
                                <span class="text-muted text-nowrap">{{ $log->created_at->translatedFormat('d M H:i') }}</span>
                            </div>
                        @empty
                            <div class="small text-muted">Belum ada aktivitas.</div>
                        @endforelse
                    </div>
                </div>
                <form method="POST" action="{{ route('whatsapp.ai-bots.destroy', $device) }}" onsubmit="return confirm('Hapus AI Bot dan API key-nya?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger w-100"><i class="ri-delete-bin-line"></i> Hapus AI Bot</button>
                </form>
            @else
                <div class="card mb-0"><div class="card-body small text-muted">Simpan dulu, lalu tes API key di sini.</div></div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Ganti penyedia -> isi contoh model bila kolom model masih kosong / masih contoh penyedia lain.
        (function () {
            var driver = document.querySelector('[data-driver]');
            var model = document.querySelector('[data-model]');
            var defaults = [].map.call(driver.options, function (o) { return o.dataset.model; });
            driver.addEventListener('change', function () {
                if (!model.value || defaults.indexOf(model.value) > -1) model.value = driver.selectedOptions[0].dataset.model;
            });
        })();
    </script>
@endpush
