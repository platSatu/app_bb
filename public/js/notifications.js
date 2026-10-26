// Lonceng header: chat WhatsApp yang belum dibaca. Dicek tiap 20 detik, berhenti saat tab tidak dilihat.
(function () {
    var root = document.querySelector('[data-notifications]');
    if (!root) return;

    var INTERVAL = 20000;
    var dot = root.querySelector('[data-dot]');
    var count = root.querySelector('[data-count]');
    var empty = root.querySelector('[data-empty]');
    var list = root.querySelector('[data-list]');
    var timer = null;
    var stopped = false;

    var escape = function (value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    };

    var ago = function (iso) {
        var seconds = Math.max(0, Math.floor((Date.now() - Date.parse(iso)) / 1000));
        if (isNaN(seconds)) return '';
        if (seconds < 60) return 'Baru saja';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' menit lalu';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' jam lalu';
        if (seconds < 604800) return Math.floor(seconds / 86400) + ' hari lalu';
        return Math.floor(seconds / 604800) + ' minggu lalu';
    };

    // Isi panel simplebar kalau sudah diinisialisasi, supaya scroll tetap jalan.
    var target = function () { return list.querySelector('.simplebar-content') || list; };

    var render = function (data) {
        var items = data.items || [];
        dot.classList.toggle('d-none', items.length === 0);
        count.textContent = data.count + (data.more ? '+' : '');
        empty.classList.toggle('d-none', items.length > 0);
        target().innerHTML = items.map(function (item) {
            return '<a href="' + escape(item.url) + '" class="notification unread d-block text-body text-decoration-none">'
                + '<div class="d-flex justify-content-between gap-2">'
                + '<h6 class="message mb-1 text-truncate">' + escape(item.name) + '</h6>'
                + '<span class="badge bg-success rounded-pill align-self-start">' + item.unread + '</span>'
                + '</div>'
                + '<p class="fs-14 text-muted mb-1 text-truncate">' + (item.preview ? escape(item.preview) : '<i>(media)</i>') + '</p>'
                + '<p class="fs-12 text-muted mb-0"><i class="ri-whatsapp-line"></i> ' + escape(item.device) + ' · ' + ago(item.at) + '</p>'
                + '</a>';
        }).join('');
    };

    var poll = function () {
        clearTimeout(timer);
        if (stopped || document.hidden) return;
        fetch(root.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (res) {
                // Sesi habis / akses dicabut: tidak perlu terus bertanya ke server.
                if ([401, 403, 419].indexOf(res.status) !== -1) stopped = true;
                return res.ok ? res.json() : null;
            })
            .then(function (data) { if (data) render(data); })
            .catch(function () {})
            .finally(function () { if (!stopped) timer = setTimeout(poll, INTERVAL); });
    };

    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
    poll();
})();
