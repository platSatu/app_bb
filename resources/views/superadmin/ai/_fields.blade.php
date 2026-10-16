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
            placeholder="{{ \App\Models\AiProvider::DRIVERS[$provider->driver ?? 'openai'][2] }}" required>
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
