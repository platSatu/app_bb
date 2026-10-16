/*!
 * bizbos-pay.js — popup pembayaran Invoice bizbos.
 * Pakai: BizbosPay.open(checkoutUrl, { onSuccess, onClose })
 * PENTING: callback di browser hanya untuk tampilan. Status lunas yang sah selalu dari webhook
 * invoice.paid atau GET /api/v1/invoices/{id} di server Anda.
 */
(function (window) {
    'use strict';

    function open(checkoutUrl, options) {
        options = options || {};
        var url = new URL(checkoutUrl);
        url.searchParams.set('popup', '1');
        url.searchParams.set('origin', window.location.origin);

        var width = 480, height = 760;
        var left = Math.max(0, (window.screen.width - width) / 2), top = Math.max(0, (window.screen.height - height) / 2);
        var popup = window.open(url.toString(), 'bizbos_pay', 'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes');

        // Popup diblokir browser: pindah halaman biasa (pembayaran tetap jalan, kembali lewat return_url).
        if (!popup) {
            window.location.href = checkoutUrl;
            return null;
        }

        var finished = false;

        function finish(handler, data) {
            if (finished) return;
            finished = true;
            clearInterval(watcher);
            window.removeEventListener('message', onMessage);
            if (typeof handler === 'function') handler(data);
        }

        function onMessage(event) {
            if (event.origin !== url.origin || !event.data || event.data.source !== 'bizbos-pay') return;
            finish(event.data.status === 'paid' ? options.onSuccess : options.onClose, event.data);
        }

        window.addEventListener('message', onMessage);

        var watcher = setInterval(function () {
            if (popup.closed) finish(options.onClose, { source: 'bizbos-pay', status: 'closed' });
        }, 700);

        return popup;
    }

    window.BizbosPay = { open: open };
})(window);
