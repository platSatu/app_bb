<div class="col-md-6">
    <label for="phone" class="form-label">Nomor WhatsApp (opsional)</label>
    <input type="tel" id="phone" name="phone" maxlength="20" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $item->phone) }}" placeholder="mis. 0812 3456 7890">
    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="col-md-6">
    <label for="email" class="form-label">Email (opsional)</label>
    <input type="email" id="email" name="email" maxlength="255" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $item->email) }}">
    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">Nanti dipakai untuk login ke dashboard {{ strtolower($config['label']) }}.</div>
</div>
