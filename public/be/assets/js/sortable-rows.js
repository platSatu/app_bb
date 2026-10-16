/*
 * Drag & drop urutan baris tanpa library (mouse + layar sentuh).
 * Pakai: <div data-sortable> berisi anak-anak [data-row], dengan pegangan [data-drag-handle].
 * Setelah urutan berubah, event 'sorted' dikirim dari container.
 */
(function () {
    function init(container) {
        var dragging = null;

        container.addEventListener('pointerdown', function (event) {
            var handle = event.target.closest('[data-drag-handle]');
            if (!handle || !container.contains(handle) || handle.closest('fieldset[disabled]')) return;
            dragging = handle.closest('[data-row]');
            if (!dragging) return;
            event.preventDefault();
            dragging.classList.add('opacity-50');
            handle.setPointerCapture(event.pointerId);
        });

        container.addEventListener('pointermove', function (event) {
            if (!dragging) return;
            var target = document.elementFromPoint(event.clientX, event.clientY);
            var row = target && target.closest('[data-row]');
            if (!row || row === dragging || row.parentNode !== container) return;
            var box = row.getBoundingClientRect();
            var after = event.clientY > box.top + box.height / 2;
            container.insertBefore(dragging, after ? row.nextSibling : row);
        });

        function stop() {
            if (!dragging) return;
            dragging.classList.remove('opacity-50');
            dragging = null;
            container.dispatchEvent(new CustomEvent('sorted'));
        }

        container.addEventListener('pointerup', stop);
        container.addEventListener('pointercancel', stop);
    }

    document.querySelectorAll('[data-sortable]').forEach(init);
})();
