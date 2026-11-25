{{-- Berat & dimensi paket (dipakai ongkir semua channel). --}}
<div class="row g-2">
    <div class="col-6">
        <label class="form-label small mb-1">Berat (kg) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" name="package_weight" class="form-control" min="0.01" value="{{ old('package_weight', '0.5') }}" required>
    </div>
    <div class="col-6">
        <label class="form-label small mb-1">Panjang (cm) <span class="text-danger">*</span></label>
        <input type="number" step="0.1" name="package_length" class="form-control" min="1" value="{{ old('package_length', 10) }}" required>
    </div>
    <div class="col-6">
        <label class="form-label small mb-1">Lebar (cm) <span class="text-danger">*</span></label>
        <input type="number" step="0.1" name="package_width" class="form-control" min="1" value="{{ old('package_width', 10) }}" required>
    </div>
    <div class="col-6">
        <label class="form-label small mb-1">Tinggi (cm) <span class="text-danger">*</span></label>
        <input type="number" step="0.1" name="package_height" class="form-control" min="1" value="{{ old('package_height', 10) }}" required>
    </div>
</div>
