/* Halaman Device & Inbox: hubungkan (QR / kode pairing), status realtime, kesehatan, disconnect. */
(function () {
    'use strict';

    var root = document.getElementById('wa-devices');
    if (!root) return;

    var api = BizWa.api;
    var toast = BizWa.toast;
    var statuses = JSON.parse(root.dataset.statuses);
    var url = function (key, id) { return root.dataset[key].replace('__ID__', id); };

    var connectModalEl = document.getElementById('wa-connect-modal');
    var connectModal = new bootstrap.Modal(connectModalEl);
    var qrBox = connectModalEl.querySelector('[data-qr]');
    var badge = connectModalEl.querySelector('[data-connect-badge]');
    var successBox = connectModalEl.querySelector('[data-connect-success]');
    var pairPhone = connectModalEl.querySelector('[data-pair-phone]');
    var pairSubmit = connectModalEl.querySelector('[data-pair-submit]');
    var pairCode = connectModalEl.querySelector('[data-pair-code]');
    var tabs = connectModalEl.querySelector('[data-connect-tabs]');
    var relinkBtn = connectModalEl.querySelector('[data-relink]');

    var activeId = null;
    var qr = null;
    var lastQr = null;
    var pollTimer = null;

    function row(id) { return root.querySelector('tr[data-device="' + id + '"]'); }

    function setBadge(text, color) {
        badge.textContent = text;
        badge.className = 'badge fs-13 px-3 py-2 bg-' + color + '-subtle text-' + color;
    }

    function renderQr(text) {
        if (!text || text === lastQr) return;
        lastQr = text;
        qrBox.innerHTML = '';
        qr = new QRCode(qrBox, { text: text, width: 256, height: 256 });
        setBadge('Menunggu scan...', 'warning');
    }

    function showMode(mode) {
        connectModalEl.querySelectorAll('[data-connect-tabs] .nav-link').forEach(function (b) { b.classList.toggle('active', b.dataset.mode === mode); });
        connectModalEl.querySelectorAll('[data-pane]').forEach(function (p) { p.classList.toggle('d-none', p.dataset.pane !== mode); });
    }

    function updateRow(id, status, reason) {
        var tr = row(id);
        var meta = statuses[status] || statuses.disconnected;
        if (!tr) return;
        var b = tr.querySelector('[data-status-badge]');
        b.className = 'badge d-inline-flex align-items-center bg-' + meta[1] + '-subtle text-' + meta[1];
        b.querySelector('.wa-status-dot').className = 'wa-status-dot ' + meta[2];
        tr.querySelector('[data-status-label]').textContent = meta[0];
        tr.querySelector('[data-status-reason]').textContent = status === 'connected' ? '' : (reason || '');
    }

    function connected() {
        stopPolling();
        setBadge('Terhubung', 'success');
        successBox.classList.remove('d-none');
        setTimeout(function () { window.location.reload(); }, 1500);
    }

    // Cadangan bila SSE terlambat: cek status & QR terbaru tiap 4 detik selama modal terbuka.
    function startPolling() {
        stopPolling();
        pollTimer = setInterval(function () {
            if (!activeId) return;
            api(url('statusUrl', activeId)).then(function (res) {
                if (!res.ok) return;
                if (res.data.data.online) return connected();
                renderQr(res.data.data.qr);
            });
        }, 4000);
    }

    function stopPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = null;
    }

    function startConnect(phone) {
        setBadge(phone ? 'Meminta kode...' : 'Menyiapkan...', 'warning');
        return api(url('connectUrl', activeId), { method: 'POST', json: { phone: phone || null } }).then(function (res) {
            if (!res.ok) {
                setBadge(res.message, 'danger');
                return;
            }
            var data = res.data.data;
            // Sudah tersambung ke WhatsApp: tidak perlu QR. Tampilkan info + pilihan scan ulang.
            if (data.mode === 'connected') return showLinked();
            if (data.mode === 'reconnect') setBadge('Menyambung ulang tanpa scan...', 'info');
            if (data.mode === 'code') {
                pairCode.textContent = data.pairing_code.replace(/(.{4})(.{4})/, '$1-$2');
                pairCode.classList.remove('d-none');
                setBadge('Masukkan kode di HP Anda', 'warning');
            }
            startPolling();
        });
    }

    function showLinked() {
        stopPolling();
        updateRow(activeId, 'connected', '');
        tabs.classList.add('d-none');
        badge.classList.add('d-none');
        showMode('linked');
    }

    // Putuskan sesi lama lalu langsung minta QR baru di modal yang sama.
    relinkBtn.addEventListener('click', function () {
        var id = activeId;
        if (!id) return;
        relinkBtn.disabled = true;
        api(url('logoutUrl', id), { method: 'POST' }).then(function (res) {
            relinkBtn.disabled = false;
            if (!res.ok) return toast(res.message, 'error');
            var tr = row(id);
            if (tr) tr.dataset.linked = '0';
            updateRow(id, 'logged_out', '');
            openConnect(id);
        });
    });

    function openConnect(id) {
        var tr = row(id);
        activeId = id;
        lastQr = null;
        qrBox.textContent = 'Menyiapkan QR...';
        successBox.classList.add('d-none');
        pairCode.classList.add('d-none');
        pairPhone.value = '';
        var linked = tr && tr.dataset.linked === '1';
        tabs.classList.toggle('d-none', linked);
        badge.classList.remove('d-none');
        showMode('qr');
        if (linked) qrBox.innerHTML = '<i class="ri-loader-4-line text-muted" style="font-size: 2rem"></i>';
        connectModal.show();
        startConnect(null);
    }

    connectModalEl.querySelector('[data-connect-tabs]').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-mode]');
        if (!btn) return;
        showMode(btn.dataset.mode);
        if (btn.dataset.mode === 'qr' && !lastQr) startConnect(null);
    });

    pairSubmit.addEventListener('click', function () {
        if (pairPhone.value.replace(/\D/g, '').length < 9) return toast('Isi nomor WhatsApp yang valid.', 'warning');
        pairSubmit.disabled = true;
        startConnect(pairPhone.value).finally(function () { pairSubmit.disabled = false; });
    });

    connectModalEl.addEventListener('hidden.bs.modal', function () {
        stopPolling();
        activeId = null;
    });

    function openHealth(id) {
        var body = document.querySelector('[data-health-body]');
        body.innerHTML = '<div class="text-center text-muted py-4">Memuat...</div>';
        new bootstrap.Modal(document.getElementById('wa-health-modal')).show();
        api(url('healthUrl', id)).then(function (res) {
            if (!res.ok) { body.innerHTML = '<div class="text-danger text-center py-3"></div>'; body.firstChild.textContent = res.message; return; }
            var h = res.data.health;
            var line = function (label, value) { return '<li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">' + label + '</span><span class="fw-semibold">' + value + '</span></li>'; };
            body.innerHTML =
                '<div class="d-flex align-items-center gap-3 mb-3">' +
                    '<div class="rounded-circle d-flex align-items-center justify-content-center bg-' + h.color + '-subtle text-' + h.color + '" style="width:64px;height:64px"><span class="fs-4 fw-semibold">' + h.score + '</span></div>' +
                    '<div><span class="badge bg-' + h.color + '-subtle text-' + h.color + ' fs-13 px-3 py-2">' + h.label + '</span><p class="text-muted small mb-0 mt-1">Skor 0-100 dari riwayat 7 hari, makin tinggi makin aman dari pembatasan WhatsApp.</p></div>' +
                '</div><ul class="list-unstyled mb-0 small">' +
                line('Dibatasi WhatsApp', h.banned + (h.banned_until ? ' (sampai ' + h.banned_until + ')' : '')) +
                line('Logout dari HP', h.logged_out) +
                line('Terputus', h.disconnected) +
                line('Pesan terkirim (7 hari)', h.sent) +
                line('Gagal / dilewati', h.failed + ' (' + h.fail_rate + '%)') +
                line('Blast hari ini', h.sent_today + ' / ' + h.daily_cap + (h.warmup_day ? ' &middot; pemanasan hari ke-' + h.warmup_day : '')) +
                '</ul>';
        });
    }

    function openEdit(tr) {
        var form = document.querySelector('[data-edit-form]');
        form.action = form.dataset.action.replace('__ID__', tr.dataset.device);
        form.elements.namedItem('name').value = tr.dataset.name;
        form.elements.namedItem('daily_cap').value = tr.dataset.cap || 0;
        new bootstrap.Modal(document.getElementById('wa-edit-modal')).show();
    }

    function logout(id, btn) {
        if (!confirm('Putuskan nomor ini dari WhatsApp? Anda perlu scan ulang untuk menghubungkannya lagi.')) return;
        btn.disabled = true;
        api(url('logoutUrl', id), { method: 'POST' }).then(function (res) {
            toast(res.ok ? 'Perangkat sudah diputuskan.' : res.message, res.ok ? 'success' : 'error');
            if (res.ok) setTimeout(function () { window.location.reload(); }, 800);
            else btn.disabled = false;
        });
    }

    root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action]');
        if (!btn) return;
        var tr = btn.closest('tr[data-device]');
        var id = tr.dataset.device;
        ({ connect: function () { openConnect(id); }, health: function () { openHealth(id); }, edit: function () { openEdit(tr); }, logout: function () { logout(id, btn); } })[btn.dataset.action]();
    });

    BizWa.realtime(root.dataset.listUrl, {
        'device.qr': function (d) { if (d.device_id === activeId) renderQr(d.qr); },
        'device.status': function (d) {
            updateRow(d.device_id, d.status, d.reason);
            if (d.device_id !== activeId) return;
            if (d.status === 'connected') connected();
            else if (d.status === 'disconnected' && d.reason) setBadge(d.reason, 'danger');
        },
    });

    if (root.dataset.autoConnect && row(root.dataset.autoConnect)) openConnect(root.dataset.autoConnect);
    if (root.dataset.openAdd) new bootstrap.Modal(document.getElementById('wa-add-modal')).show();
})();
