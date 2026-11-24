@php($uid = $provider->id ?? 'new')
<div class="row g-2">
    <div class="col-md-6">
        <label class="form-label small mb-1">Nama</label>
        <input type="text" name="name" maxlength="100" value="{{ $provider->name }}" class="form-control form-control-sm" placeholder="mis. OpenAI Utama" required>
    </div>
    <div class="col-md-6">
        <label class="form-label small mb-1">Penyedia</label>
        <select name="driver" class="form-select form-select-sm" data-driver="{{ $uid }}">
            @foreach (\App\Models\AiProvider::DRIVERS as $key => [$label, $url, $model])
                <option value="{{ $key }}" data-model="{{ $model }}" @selected($provider->driver === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label small mb-1">Model</label>
        <input type="text" name="model" maxlength="100" value="{{ $provider->model }}" class="form-control form-control-sm" data-model-input="{{ $uid }}"
            placeholder="{{ \App\Models\AiProvider::DRIVERS[$provider->driver ?? 'openai'][2] }}" required
            @if ($provider->exists && $provider->isClassai()) list="classai-models-{{ $uid }}" @endif>
        @if ($provider->exists && $provider->isClassai())
            {{-- Diisi lewat tombol di bawah dari GET /v1/models Classai (lihat script di index). --}}
            <datalist id="classai-models-{{ $uid }}"></datalist>
            <div class="d-flex flex-wrap gap-1 mt-1" data-classai-tools
                data-models-url="{{ route('superadmin.ai.models', $provider) }}"
                data-speed-url="{{ route('superadmin.ai.speed', $provider) }}"
                data-list="classai-models-{{ $uid }}" data-input="{{ $uid }}">
                <button type="button" class="btn btn-sm btn-outline-secondary py-0" data-load-models><i class="ri-list-check"></i> Lihat model yang tersedia</button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0" data-speed><i class="ri-timer-line"></i> Cek kecepatan model ini</button>
            </div>
            <div class="small text-muted mt-1" data-classai-status></div>
            <ul class="small list-unstyled mb-0 mt-1" data-speed-results></ul>
        @else
            @if (! $provider->exists)
                <div class="form-text small">Pakai Classai Router? Pilih "Classai Router" di kolom Penyedia, tempel API key, klik Tambah. Setelah itu Anda bisa memilih model dari daftar dan mengecek kecepatannya.</div>
            @endif
        @endif
    </div>
    <div class="col-md-6">
        <label class="form-label small mb-1">Base URL (opsional)</label>
        <input type="url" name="base_url" maxlength="255" value="{{ $provider->base_url }}" class="form-control form-control-sm" placeholder="Kosong = alamat resmi penyedia">
    </div>
    <div class="col-12">
        <label class="form-label small mb-1">API Key</label>
        <input type="password" name="api_key" maxlength="500" autocomplete="new-password" class="form-control form-control-sm"
            placeholder="{{ $provider->exists ? 'Sudah tersimpan, isi untuk mengganti' : 'Tempel API key' }}" @required(! $provider->exists)>
    </div>
</div>
<script>
    document.querySelector('[data-driver="{{ $uid }}"]').addEventListener('change', function () {
        var input = document.querySelector('[data-model-input="{{ $uid }}"]');
        input.placeholder = this.selectedOptions[0].dataset.model;
        if (!input.value) input.value = this.selectedOptions[0].dataset.model;
    });
</script>
