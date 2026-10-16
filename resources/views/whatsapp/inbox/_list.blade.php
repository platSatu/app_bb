{{-- Kiri: daftar chat --}}
<div class="wa-col wa-col-contacts">
    <div class="wa-contacts-toolbar">
        <a href="{{ route('whatsapp.devices.index', ['branch' => $device->branch_id]) }}" class="wa-icon-btn" title="Kembali ke daftar device"><i class="ri-arrow-left-line"></i></a>
        <div class="wa-chat-filter">
            <span class="text-truncate">{{ $device->name }}</span>
            <span id="wa-chat-count" class="text-muted">(0)</span>
        </div>
        <button type="button" id="wa-new-chat-btn" class="wa-new-chat-btn" title="Mulai chat baru"><i class="ri-add-line"></i> Baru</button>
    </div>

    <div class="wa-search-wrap">
        <i class="ri-search-line"></i>
        <input type="text" id="wa-search-input" placeholder="Cari chat..." maxlength="100">
    </div>

    <div class="wa-chat-tabs" id="wa-chat-tabs">
        <button type="button" class="wa-chat-tab active" data-tab="chat">Chat</button>
        <button type="button" class="wa-chat-tab" data-tab="group">Grup</button>
        <button type="button" class="wa-chat-tab" data-tab="unread">Belum dibaca</button>
    </div>

    <div id="wa-chat-list" class="wa-chat-list">
        <div class="wa-chat-list-empty" id="wa-chat-list-empty">Memuat percakapan...</div>
    </div>
</div>
