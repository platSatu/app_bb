/* Helper bersama halaman WhatsApp bizbos: request JSON, notifikasi, dan koneksi realtime (SSE go_bizbos). */
window.BizWa = (function () {
    'use strict';

    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    // Selalu resolve {ok, status, data, message}; pesan error diambil dari jawaban server.
    function api(url, options) {
        options = options || {};
        var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        var body = options.body;
        if (options.method && options.method !== 'GET') headers['X-CSRF-TOKEN'] = csrf;
        if (options.json) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(options.json);
        }
        return fetch(url, { method: options.method || 'GET', headers: headers, body: body, credentials: 'same-origin' })
            .then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    var message = data.message;
                    if (data.errors) message = data.errors[Object.keys(data.errors)[0]][0];
                    if (res.status === 419) message = 'Sesi Anda berakhir, silakan muat ulang halaman.';
                    return { ok: res.ok && data.success !== false, status: res.status, data: data, message: message || (res.ok ? '' : 'Terjadi kesalahan, silakan coba lagi.') };
                });
            })
            .catch(function () {
                return { ok: false, status: 0, data: {}, message: 'Koneksi internet terputus, silakan coba lagi.' };
            });
    }

    function toast(message, type) {
        if (!message) return;
        var wrap = document.getElementById('bizwa-toasts');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'bizwa-toasts';
            wrap.className = 'position-fixed bottom-0 end-0 p-3';
            wrap.style.zIndex = 1200;
            document.body.appendChild(wrap);
        }
        var color = { success: 'success', error: 'danger', warning: 'warning', info: 'info' }[type || 'info'];
        var el = document.createElement('div');
        el.className = 'alert alert-' + color + ' shadow-sm py-2 px-3 mb-2 small d-flex align-items-center gap-2';
        el.setAttribute('role', 'alert');
        el.style.maxWidth = '360px';
        el.textContent = message;
        wrap.appendChild(el);
        setTimeout(function () { el.remove(); }, type === 'error' ? 7000 : 4000);
    }

    /**
     * Sambungan SSE ke go_bizbos. tokenUrl mengembalikan {url} (atau {realtime_url}) dengan token singkat;
     * saat putus, token baru diminta lalu disambung ulang (makin lama makin jarang).
     * handlers: { 'nama.event': fn(data) }, plus onState(true|false) opsional.
     */
    function realtime(tokenUrl, handlers, onState) {
        var es = null;
        var retry = 0;
        var stopped = false;

        function connect() {
            if (stopped) return;
            api(tokenUrl).then(function (res) {
                var url = res.ok && (res.data.url || res.data.realtime_url);
                if (!url) return schedule();
                es = new EventSource(url);
                es.onopen = function () { retry = 0; if (onState) onState(true); };
                es.onerror = function () { es.close(); if (onState) onState(false); schedule(); };
                es.addEventListener('reconnect', function () { es.close(); connect(); });
                Object.keys(handlers).forEach(function (name) {
                    es.addEventListener(name, function (e) {
                        var data = {};
                        try { data = JSON.parse(e.data); } catch (err) { return; }
                        handlers[name](data);
                    });
                });
            });
        }

        function schedule() {
            if (stopped) return;
            retry = Math.min(retry + 1, 6);
            setTimeout(connect, Math.pow(2, retry) * 1000);
        }

        connect();
        window.addEventListener('beforeunload', function () { stopped = true; if (es) es.close(); });
    }

    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'r' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
    }

    // Tombol salin: <button data-copy="teks">.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy]');
        if (!btn) return;
        var text = btn.getAttribute('data-copy');
        var done = function () { toast('Disalin ke clipboard.', 'success'); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            var temp = document.createElement('textarea');
            temp.value = text;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            temp.remove();
            done();
        }
    });

    return { api: api, toast: toast, realtime: realtime, uuid: uuid };
})();
