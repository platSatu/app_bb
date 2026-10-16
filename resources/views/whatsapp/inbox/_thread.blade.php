{{-- Tengah: percakapan --}}
<div class="wa-col wa-col-thread">
    <div id="wa-thread-header" class="wa-thread-header d-none">
        <div class="wa-thread-header-left">
            <button type="button" id="wa-thread-back-btn" class="wa-icon-btn wa-thread-back-btn" title="Kembali ke daftar chat"><i class="ri-arrow-left-line"></i></button>
            <div id="wa-thread-avatar" class="wa-avatar-circle"></div>
            <div class="wa-thread-header-info">
                <h6 id="wa-thread-title" class="mb-0 text-truncate"></h6>
                <small id="wa-thread-sub" class="text-muted text-truncate d-block"></small>
            </div>
        </div>
        <div class="wa-thread-header-right">
            <span id="wa-thread-presence-pill" class="wa-presence-pill wa-pill-offline">Status tidak diketahui</span>
            <button type="button" id="wa-thread-search-btn" class="wa-icon-btn" title="Cari di percakapan"><i class="ri-search-line"></i></button>
            <button type="button" id="wa-toggle-detail-btn" class="wa-icon-btn" title="Tampilkan/sembunyikan detail"><i class="ri-layout-right-line"></i></button>
        </div>
    </div>

    <div id="wa-thread-search-bar" class="wa-thread-search-bar d-none">
        <i class="ri-search-line"></i>
        <input type="text" id="wa-thread-search-input" placeholder="Cari di pesan yang sudah dimuat...">
        <span id="wa-thread-search-count" class="wa-thread-search-count"></span>
        <button type="button" id="wa-thread-search-prev" class="wa-icon-btn" title="Sebelumnya"><i class="ri-arrow-up-s-line"></i></button>
        <button type="button" id="wa-thread-search-next" class="wa-icon-btn" title="Berikutnya"><i class="ri-arrow-down-s-line"></i></button>
        <button type="button" id="wa-thread-search-close" class="wa-icon-btn" title="Tutup"><i class="ri-close-line"></i></button>
    </div>

    <div id="wa-thread-empty" class="wa-thread-empty">
        <i class="ri-chat-3-line"></i>
        <p>Pilih percakapan di sebelah kiri untuk mulai chat.</p>
    </div>

    <div id="wa-thread-body" class="wa-thread-body d-none"></div>

    <div id="wa-attach-preview" class="wa-attach-preview">
        <i class="ri-file-3-line" id="wa-attach-preview-icon"></i>
        <span class="wa-attach-preview-name" id="wa-attach-preview-name"></span>
        <button type="button" id="wa-attach-preview-cancel">Batal</button>
    </div>

    <form id="wa-send-form" class="wa-send-form d-none">
        <input type="file" id="wa-attach-input" class="d-none" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt,.csv">
        <div class="wa-composer-card">
            <textarea id="wa-send-input" class="wa-send-input" placeholder="Ketik pesan... ( / untuk balasan cepat)" autocomplete="off" rows="1" maxlength="10000"></textarea>
            <div class="wa-composer-toolbar">
                <div class="wa-send-icons">
                    <button type="button" id="wa-emoji-btn" class="wa-icon-btn" title="Emoji"><i class="ri-emotion-line"></i></button>
                    <button type="button" id="wa-quick-reply-btn" class="wa-icon-btn" title="Balasan cepat"><i class="ri-flashlight-line"></i></button>
                    <button type="button" id="wa-poll-btn" class="wa-icon-btn" title="Buat survei/poll" data-bs-toggle="modal" data-bs-target="#wa-poll-modal"><i class="ri-bar-chart-box-line"></i></button>
                    <button type="button" id="wa-attach-btn" class="wa-icon-btn" title="Lampirkan file"><i class="ri-attachment-2"></i></button>
                </div>
                <button type="submit" class="wa-send-btn" title="Kirim"><span class="wa-send-btn-label">Kirim</span><i class="ri-send-plane-2-fill"></i></button>
            </div>
        </div>
    </form>

    <div id="wa-picker-popover" class="wa-picker-popover d-none">
        <div class="wa-picker-popover-header">
            <span id="wa-picker-popover-title"></span>
            <button type="button" id="wa-picker-popover-close" class="wa-icon-btn"><i class="ri-close-line"></i></button>
        </div>
        <div id="wa-picker-popover-body" class="wa-picker-popover-body"></div>
    </div>
</div>
