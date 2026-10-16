{{-- Tambah device --}}
<div class="modal fade" id="wa-add-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('whatsapp.devices.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Tambah Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-select mb-3">
                    @foreach ($branches as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
                <label class="form-label">Nama perangkat</label>
                <input type="text" name="name" maxlength="100" required class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Contoh: CS Utama">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Hanya untuk penanda di bizbos, tidak terlihat oleh pelanggan.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary">Simpan & Hubungkan</button>
            </div>
        </form>
    </div>
</div>

{{-- Ubah nama & batas blast --}}
<div class="modal fade" id="wa-edit-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="#" class="modal-content" data-edit-form data-action="{{ route('whatsapp.devices.update', '__ID__') }}">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Ubah Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Nama perangkat</label>
                <input type="text" name="name" maxlength="100" required class="form-control mb-3">
                <label class="form-label">Batas blast per hari</label>
                <input type="number" name="daily_cap" min="0" max="5000" class="form-control">
                <div class="form-text">Isi 0 untuk memakai batas aman bawaan. Nomor baru otomatis dinaikkan bertahap 20, 40, 60 pesan per hari.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Hubungkan: QR atau kode pairing --}}
<div class="modal fade" id="wa-connect-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hubungkan WhatsApp</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <ul class="nav nav-pills nav-justified mb-3" data-connect-tabs>
                    <li class="nav-item"><button type="button" class="nav-link active" data-mode="qr"><i class="ri-qr-code-line"></i> Scan QR</button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-mode="code"><i class="ri-smartphone-line"></i> Kode Pairing</button></li>
                </ul>

                <div data-pane="qr">
                    <p class="text-muted small mb-3">Buka WhatsApp di HP &rsaquo; <strong>Perangkat tertaut</strong> &rsaquo; <strong>Tautkan perangkat</strong>, lalu arahkan kamera ke QR ini.</p>
                    <div class="d-flex justify-content-center mb-3"><div data-qr style="min-height: 256px; min-width: 256px" class="d-flex align-items-center justify-content-center text-muted">Menyiapkan QR...</div></div>
                </div>

                <div data-pane="code" class="d-none">
                    <p class="text-muted small mb-3">Masukkan nomor WhatsApp yang akan ditautkan. Kode 8 digit akan muncul, ketik di HP lewat <strong>Perangkat tertaut</strong> &rsaquo; <strong>Tautkan dengan nomor telepon</strong>.</p>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" inputmode="numeric" placeholder="Contoh: 081234567890" data-pair-phone>
                        <button type="button" class="btn btn-primary" data-pair-submit>Minta Kode</button>
                    </div>
                    <div class="wa-pair-code d-none mb-2" data-pair-code></div>
                </div>

                <div class="badge bg-warning-subtle text-warning fs-13 px-3 py-2" data-connect-badge>Menunggu...</div>
                <div class="d-none mt-3" data-connect-success>
                    <i class="ri-checkbox-circle-fill text-success" style="font-size: 3rem"></i>
                    <p class="fw-semibold mb-0">WhatsApp berhasil terhubung!</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Kesehatan nomor --}}
<div class="modal fade" id="wa-health-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kesehatan Nomor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" data-health-body>
                <div class="text-center text-muted py-4">Memuat...</div>
            </div>
        </div>
    </div>
</div>
