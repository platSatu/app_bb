{{-- Kanan: detail kontak. Di bawah 1200px menjadi panel geser. --}}
<div class="wa-detail-backdrop" id="wa-detail-backdrop"></div>
<div class="wa-col wa-col-detail" id="wa-detail-panel">
    <button type="button" class="wa-detail-close-btn" id="wa-detail-close-btn" title="Tutup detail"><i class="ri-close-line"></i></button>
    <div id="wa-detail-empty" class="wa-detail-empty text-muted">Pilih percakapan untuk melihat detail.</div>

    <div id="wa-detail-content" class="d-none">
        <div class="wa-detail-header">
            <div id="wa-detail-avatar" class="wa-avatar-circle wa-avatar-lg"></div>
            <h6 id="wa-detail-name" class="mb-1"></h6>
            <div class="text-muted small" id="wa-detail-phone"></div>
            <div class="text-muted small d-none" id="wa-detail-contact"></div>
            <div class="wa-detail-save-actions">
                <a href="#" id="wa-detail-save-contact" class="wa-detail-save-contact-btn d-none" download><i class="ri-user-add-line"></i> + Phone</a>
                <button type="button" id="wa-detail-save-phonebook-btn" class="wa-detail-save-contact-btn wa-detail-save-phonebook-btn d-none"><i class="ri-contacts-book-line"></i> Buku Kontak</button>
            </div>

            <div id="wa-save-phonebook-popover" class="wa-save-phonebook-popover d-none">
                <div class="wa-save-phonebook-popover-title">Simpan ke Buku Kontak</div>
                <label class="wa-save-phonebook-label" for="wa-save-phonebook-name">Nama</label>
                <input id="wa-save-phonebook-name" class="wa-save-phonebook-select" maxlength="150">
                <label class="wa-save-phonebook-label" for="wa-save-phonebook-category">Group (opsional)</label>
                <select id="wa-save-phonebook-category" class="wa-save-phonebook-select"><option value="">Tanpa group</option></select>
                <div class="text-danger small d-none" id="wa-save-phonebook-error"></div>
                <div class="wa-save-phonebook-popover-actions">
                    <button type="button" id="wa-save-phonebook-cancel-btn" class="btn btn-sm btn-light">Batal</button>
                    <button type="button" id="wa-save-phonebook-confirm-btn" class="btn btn-sm btn-success">Simpan</button>
                </div>
            </div>
        </div>

        <div class="wa-detail-section">
            <div class="wa-detail-section-title"><span><i class="ri-user-add-line"></i> ASSIGNMENT</span></div>
            <select id="wa-contact-assign-select" class="wa-assign-select" disabled><option value="">Memuat...</option></select>
        </div>

        <div class="wa-detail-section">
            <div class="wa-detail-section-title"><span><i class="ri-robot-2-line"></i> BOT</span></div>
            <select id="wa-bot-select" class="wa-assign-select">
                <option value="0">Bot aktif (flow, auto reply, AI)</option>
                <option value="1">Jeda bot 1 jam</option>
                <option value="24">Jeda bot 24 jam</option>
                <option value="168">Jeda bot 7 hari</option>
            </select>
            <div class="text-muted small mt-1 d-none" id="wa-bot-status"></div>
        </div>

        <div class="wa-detail-section wa-label-section">
            <div class="wa-detail-section-title">
                <span><i class="ri-price-tag-3-line"></i> LABELS</span>
                <button type="button" class="wa-label-add-btn" id="wa-label-add-btn">+ Add</button>
            </div>
            <div id="wa-label-chips" class="wa-label-chips"></div>
            <div class="text-muted small fst-italic d-none" id="wa-label-empty">Belum ada label ditempel.</div>
            <div class="wa-label-picker d-none" id="wa-label-picker"><div id="wa-label-picker-list"></div></div>
        </div>

        <div class="wa-detail-section">
            <div class="wa-detail-section-title"><span><i class="ri-image-line"></i> MEDIA &amp; FILES</span></div>
            <div class="wa-media-tabs" id="wa-media-tabs">
                <span class="wa-media-tab active" data-media-type="image">Foto</span>
                <span class="wa-media-tab" data-media-type="video">Video</span>
                <span class="wa-media-tab" data-media-type="document">Dokumen</span>
            </div>
            <div id="wa-media-grid" class="wa-media-grid"></div>
            <div class="text-muted small fst-italic d-none" id="wa-media-empty">Belum ada media.</div>
        </div>

        <div class="wa-detail-section">
            <div class="wa-detail-section-title">
                <span><i class="ri-file-text-line"></i> NOTES</span>
                <button type="button" class="wa-label-add-btn" id="wa-note-add-btn">+ Add</button>
            </div>
            <div id="wa-note-form" class="wa-note-form d-none">
                <textarea id="wa-note-input" class="wa-note-textarea" rows="2" placeholder="Tulis catatan tentang kontak/chat ini..." maxlength="2000"></textarea>
                <div class="wa-note-form-actions">
                    <button type="button" class="wa-modal-btn-cancel" id="wa-note-cancel">Batal</button>
                    <button type="button" class="wa-modal-btn-primary" id="wa-note-save">Simpan</button>
                </div>
            </div>
            <div id="wa-note-list" class="wa-note-list"></div>
            <div class="text-muted small fst-italic d-none" id="wa-note-empty">Belum ada catatan.</div>
        </div>
    </div>
</div>
