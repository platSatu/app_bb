{{-- Isian nama, WhatsApp, email untuk modal tambah/ubah customer. --}}
<label class="form-label">Nama</label>
<input type="text" name="name" maxlength="150" required class="form-control mb-3 @error('name') is-invalid @enderror" value="{{ old('name') }}">
<label class="form-label">Nomor WhatsApp</label>
<input type="text" name="phone" maxlength="20" inputmode="numeric" class="form-control mb-1 @error('phone') is-invalid @enderror" value="{{ old('phone') }}" placeholder="081234567890">
@error('phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
<div class="form-text mb-3">Otomatis disimpan dengan awalan 62.</div>
<label class="form-label">Email</label>
<input type="email" name="email" maxlength="255" class="form-control mb-1 @error('email') is-invalid @enderror" value="{{ old('email') }}">
@error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
<div class="form-text mb-3">Wajib diisi kalau nanti mau dijadikan user (bisa login).</div>
