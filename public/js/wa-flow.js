/* Editor Chatbot Flow: langkah (pesan + pilihan) disusun di browser, dikirim sebagai JSON lalu divalidasi server. */
(function () {
    'use strict';

    var form = document.getElementById('wa-flow-form');
    if (!form) return;
    var input = form.querySelector('[data-nodes-input]');
    var wrap = form.querySelector('[data-nodes]');
    var nodes = [];
    try { nodes = JSON.parse(input.value) || []; } catch (e) { nodes = []; }
    if (!nodes.length) nodes = [{ id: 'start', message: '', options: [] }];

    var el = function (tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    };

    function nextId() {
        var i = nodes.length;
        while (nodes.some(function (n) { return n.id === 'langkah' + i; })) i++;
        return 'langkah' + i;
    }

    function render() {
        wrap.innerHTML = '';
        nodes.forEach(function (node, index) {
            var card = el('div', 'card mb-3');
            var body = el('div', 'card-body');

            var head = el('div', 'd-flex gap-2 align-items-center mb-2');
            var id = el('input', 'form-control form-control-sm font-monospace');
            id.style.maxWidth = '180px';
            id.value = node.id;
            id.readOnly = node.id === 'start';
            id.title = 'Kode langkah (huruf kecil/angka)';
            id.addEventListener('change', function () {
                var old = node.id;
                node.id = id.value.toLowerCase().replace(/[^a-z0-9_-]/g, '').slice(0, 40) || old;
                nodes.forEach(function (n) { (n.options || []).forEach(function (o) { if (o.next === old) o.next = node.id; }); });
                render();
            });
            head.append(el('span', 'badge bg-primary-subtle text-primary', node.id === 'start' ? 'Pembuka' : 'Langkah ' + (index + 1)), id);
            if (node.id !== 'start') {
                var del = el('button', 'btn btn-sm btn-light text-danger ms-auto');
                del.type = 'button';
                del.appendChild(el('i', 'ri-delete-bin-line'));
                del.addEventListener('click', function () { nodes.splice(index, 1); render(); });
                head.appendChild(del);
            }

            var message = el('textarea', 'form-control mb-2');
            message.rows = 3;
            message.maxLength = 2000;
            message.placeholder = 'Pesan yang dikirim, mis. Silakan pilih layanan:';
            message.value = node.message || '';
            message.addEventListener('input', function () { node.message = message.value; });

            var table = el('div', 'd-flex flex-column gap-2');
            (node.options = node.options || []).forEach(function (opt, oi) { table.appendChild(optionRow(node, opt, oi)); });

            var add = el('button', 'btn btn-sm btn-light mt-2');
            add.type = 'button';
            add.append(el('i', 'ri-add-line'), document.createTextNode(' Tambah Pilihan'));
            add.disabled = node.options.length >= 10;
            add.addEventListener('click', function () {
                node.options.push({ key: String(node.options.length + 1), label: '', action: 'end', next: null });
                render();
            });

            body.append(head, message, table, add);
            if (!node.options.length) body.appendChild(el('div', 'form-text', 'Tanpa pilihan: flow selesai setelah pesan ini dikirim.'));
            card.appendChild(body);
            wrap.appendChild(card);
        });
    }

    function optionRow(node, opt, index) {
        var row = el('div', 'd-flex flex-wrap gap-2 align-items-center');
        var key = el('input', 'form-control form-control-sm');
        key.style.width = '60px';
        key.maxLength = 10;
        key.value = opt.key;
        key.addEventListener('input', function () { opt.key = key.value; });

        var label = el('input', 'form-control form-control-sm flex-grow-1');
        label.style.minWidth = '160px';
        label.maxLength = 100;
        label.placeholder = 'Teks pilihan';
        label.value = opt.label;
        label.addEventListener('input', function () { opt.label = label.value; });

        var action = el('select', 'form-select form-select-sm w-auto');
        [['next', 'Lanjut ke'], ['end', 'Selesai'], ['handover', 'Teruskan ke admin']].forEach(function (a) { action.appendChild(new Option(a[1], a[0], false, opt.action === a[0])); });
        var next = el('select', 'form-select form-select-sm w-auto');
        nodes.filter(function (n) { return n !== node; }).forEach(function (n) { next.appendChild(new Option(n.id, n.id, false, opt.next === n.id)); });
        next.classList.toggle('d-none', opt.action !== 'next');
        if (opt.action === 'next' && !opt.next && next.options.length) opt.next = next.value;
        action.addEventListener('change', function () {
            opt.action = action.value;
            opt.next = action.value === 'next' ? next.value || null : null;
            next.classList.toggle('d-none', action.value !== 'next');
        });
        next.addEventListener('change', function () { opt.next = next.value; });

        var del = el('button', 'btn btn-sm btn-light text-danger');
        del.type = 'button';
        del.appendChild(el('i', 'ri-close-line'));
        del.addEventListener('click', function () { node.options.splice(index, 1); render(); });

        row.append(key, label, action, next, del);
        return row;
    }

    form.querySelector('[data-add-node]').addEventListener('click', function () {
        if (nodes.length >= 30) return;
        nodes.push({ id: nextId(), message: '', options: [] });
        render();
    });
    form.addEventListener('submit', function () { input.value = JSON.stringify(nodes); });

    render();
})();
