@include('schedule._contact-fields')
<div class="col-md-6">
    <label for="guardian_name" class="form-label">Nama Orang Tua / Wali (opsional)</label>
    <input type="text" id="guardian_name" name="guardian_name" maxlength="150" class="form-control @error('guardian_name') is-invalid @enderror" value="{{ old('guardian_name', $item->guardian_name) }}">
    @error('guardian_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
