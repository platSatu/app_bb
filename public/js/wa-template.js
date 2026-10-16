// Form template: tombol format WA, penghitung karakter, dan pratinjau langsung.
(function () {
    var form = document.getElementById('wa-template-form');
    if (!form) return;

    var field = function (name) { return form.querySelector('[data-field="' + name + '"]'); };
    var preview = function (name) { return form.querySelector('[data-preview="' + name + '"]'); };
    var body = field('body');
    var objectUrl = null;

    var escape = function (text) {
        return text.replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    };

    // Format WhatsApp: ```mono```, *tebal*, _miring_, ~coret~ (setelah di-escape, jadi aman).
    var format = function (text) {
        return escape(text)
            .replace(/```([\s\S]+?)```/g, '<code>$1</code>')
            .replace(/\*([^*\n]+)\*/g, '<b>$1</b>')
            .replace(/(^|[^\w])_([^_\n]+)_(?!\w)/g, '$1<i>$2</i>')
            .replace(/~([^~\n]+)~/g, '<s>$1</s>')
            .replace(/\{nama\}/g, 'Budi');
    };

    var setText = function (name, value, html) {
        var el = preview(name);
        el.hidden = !value;
        if (html) el.innerHTML = value; else el.textContent = value;
    };

    var renderFile = function () {
        var box = preview('file');
        var input = field('file');
        var remove = field('remove_file');
        var picked = input.files && input.files[0];
        var kind = form.dataset.fileKind;
        var url = form.dataset.fileUrl;
        var name = form.dataset.fileName;

        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
        if (picked) {
            kind = picked.type.indexOf('image/') === 0 ? 'image' : 'document';
            name = picked.name;
            url = kind === 'image' ? (objectUrl = URL.createObjectURL(picked)) : '';
        } else if (!url || (remove && remove.checked)) {
            box.hidden = true;
            box.innerHTML = '';
            return;
        }

        box.hidden = false;
        box.innerHTML = kind === 'image' && url
            ? '<img alt="">'
            : '<div class="wa-bubble-doc"><i class="ri-file-text-line fs-4"></i><span></span></div>';
        if (kind === 'image' && url) box.querySelector('img').src = url;
        else box.querySelector('span').textContent = name || 'Lampiran';
    };

    var render = function () {
        ['header', 'body', 'footer'].forEach(function (name) {
            var el = field(name);
            form.querySelector('[data-count="' + name + '"]').textContent = el.value.length + '/' + el.maxLength;
        });
        setText('header', field('header').value.trim());
        setText('body', body.value.trim() ? format(body.value) : 'Isi pesan akan muncul di sini...', true);
        setText('footer', field('footer').value.trim());
        var link = field('link').value.trim();
        setText('link', link);
        if (/^https?:\/\//i.test(link)) preview('link').href = link; else preview('link').removeAttribute('href');
        renderFile();
    };

    var insert = function (before, after) {
        var start = body.selectionStart, end = body.selectionEnd, value = body.value;
        var selected = value.slice(start, end);
        body.value = value.slice(0, start) + before + selected + after + value.slice(end);
        body.focus();
        body.setSelectionRange(start + before.length, start + before.length + selected.length);
        render();
    };

    form.querySelectorAll('[data-wrap]').forEach(function (btn) {
        btn.addEventListener('click', function () { insert(btn.dataset.wrap, btn.dataset.wrap); });
    });
    form.querySelectorAll('[data-insert]').forEach(function (btn) {
        btn.addEventListener('click', function () { insert(btn.dataset.insert, ''); });
    });
    form.addEventListener('input', render);
    form.addEventListener('change', render);
    form.addEventListener('submit', function () { form.querySelector('button:not([type])').disabled = true; });
    render();
})();
