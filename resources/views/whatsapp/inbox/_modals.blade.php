{{-- Buat polling --}}
<div class="modal fade" id="wa-poll-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buat Survei / Poll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Pertanyaan</label>
                <input type="text" id="wa-poll-question" class="form-control mb-3" placeholder="Contoh: Pilih jadwal yang Anda inginkan" maxlength="255">
                <label class="form-label">Pilihan Jawaban</label>
                <div id="wa-poll-options-list" class="d-flex flex-column gap-2 mb-2"></div>
                <button type="button" class="btn btn-sm btn-light" id="wa-poll-option-add"><i class="ri-add-line"></i> Tambah Pilihan</button>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="wa-poll-multi">
                    <label class="form-check-label small" for="wa-poll-multi">Boleh pilih lebih dari satu</label>
                </div>
                <div class="text-danger small mt-2 d-none" id="wa-poll-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="wa-poll-send-btn"><i class="ri-send-plane-2-line"></i> Kirim Survei</button>
            </div>
        </div>
    </div>
</div>

{{-- Hasil polling --}}
<div class="modal fade" id="wa-poll-results-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hasil Survei</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="fw-semibold mb-3" id="wa-poll-results-question"></p>
                <div id="wa-poll-results-body"></div>
                <p class="text-muted small mt-3 mb-0" id="wa-poll-results-count"></p>
            </div>
        </div>
    </div>
</div>

{{-- Chat baru --}}
<div class="wa-modal-overlay d-none" id="wa-new-chat-overlay">
    <div class="wa-modal-box">
        <div class="wa-modal-header">
            <h6 class="mb-0">Mulai Chat Baru</h6>
            <button type="button" class="wa-icon-btn" id="wa-new-chat-close" title="Tutup"><i class="ri-close-line"></i></button>
        </div>
        <div class="wa-modal-body">
            <label class="wa-modal-label" for="wa-new-chat-input">Nomor WhatsApp</label>
            <input type="text" id="wa-new-chat-input" class="wa-modal-input" placeholder="Contoh: 081234567890" inputmode="numeric" autocomplete="off">
            <div class="wa-modal-hint">Boleh diawali 0, 62, atau +62.</div>
            <label class="wa-modal-label mt-3" for="wa-new-chat-group">Atau pilih Grup WhatsApp</label>
            <select id="wa-new-chat-group" class="wa-modal-input">
                <option value="">Memuat grup...</option>
            </select>
            <div class="text-danger small mt-1 d-none" id="wa-new-chat-error"></div>
        </div>
        <div class="wa-modal-footer">
            <button type="button" class="wa-modal-btn-cancel" id="wa-new-chat-cancel">Batal</button>
            <button type="button" class="wa-modal-btn-primary" id="wa-new-chat-start">Mulai Chat</button>
        </div>
    </div>
</div>

{{-- Lightbox media --}}
<div class="wa-modal-overlay d-none wa-lightbox-overlay" id="wa-media-lightbox-overlay">
    <button type="button" class="wa-lightbox-close" id="wa-media-lightbox-close" title="Tutup"><i class="ri-close-line"></i></button>
    <div class="wa-lightbox-content" id="wa-media-lightbox-content"></div>
</div>
