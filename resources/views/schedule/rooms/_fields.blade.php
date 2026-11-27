{{-- Ruang kelas online: pengajar absen datang lewat WhatsApp ("mulai"), tanpa QR tempat & cek lokasi. --}}
<div class="col-md-6">
    <label class="form-label d-block">Kelas online</label>
    <div class="form-check form-switch">
        <input type="checkbox" role="switch" class="form-check-input" id="is_online" name="is_online" value="1" @checked(old('is_online', $item->is_online))>
        <label class="form-check-label small text-muted" for="is_online">Ruang ini untuk kelas online. Pengajar absen dengan membalas pesan WhatsApp saat mulai mengajar.</label>
    </div>
</div>
