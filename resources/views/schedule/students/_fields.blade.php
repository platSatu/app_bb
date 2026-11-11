@include('schedule._contact-fields')
<div class="col-md-6">
    <label for="guardian_name" class="form-label">Nama Orang Tua / Wali (opsional)</label>
    <input type="text" id="guardian_name" name="guardian_name" maxlength="150" class="form-control @error('guardian_name') is-invalid @enderror" value="{{ old('guardian_name', $item->guardian_name) }}">
    @error('guardian_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="col-md-6">
    <label for="left_on" class="form-label">Tanggal Keluar / Resign</label>
    <input type="date" id="left_on" name="left_on" class="form-control @error('left_on') is-invalid @enderror" value="{{ old('left_on', $item->left_on?->format('Y-m-d')) }}">
    <div class="form-text">Diisi saat status diubah menjadi Tidak Aktif (kosong = hari ini). Murid dilepas dari jadwal & sesi mulai tanggal ini; riwayat absensi tetap tersimpan.</div>
    @error('left_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
