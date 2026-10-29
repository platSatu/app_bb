/*
 * Inbox WhatsApp bizbos. Tampilan & alur mengikuti inbox teleios, tapi update datang lewat SSE go_bizbos
 * (pesan baru, status centang, status online) sehingga tidak perlu polling tiap beberapa detik.
 * Semua teks dari pengguna ditulis lewat textContent (aman dari XSS).
 */
(function () {
    'use strict';

    var root = document.getElementById('wa-inbox');
    if (!root) return;

    var U = JSON.parse(root.dataset.urls);
    var api = BizWa.api;
    var toast = BizWa.toast;
    var $ = function (id) { return document.getElementById(id); };
    var u = function (key, chatId, id) { return U[key].replace('__CHAT__', chatId || '').replace('__ID__', id || ''); };
    var el = function (tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    };

    // ---------- state ----------
    var chats = [];
    var chatIndex = new Map();
    var active = null;
    var activeTab = 'chat';
    var searchTerm = '';
    var renderedSig = '';
    var threadMsgs = [];
    var hasMore = false;
    var loadingOlder = false;
    var pending = new Map();      // client_ref -> {row, retry}
    var pendingByOutbox = new Map();
    var detail = { labels: [], notes: [], team: [], groups: [], contact: null, assigned: null };
    var mediaType = 'image';
    var pendingFile = null;
    var fallbackTimer = null;
    var historyTimer = null;
    var wasLive = false;

    // ---------- elements ----------
    var chatListEl = $('wa-chat-list');
    var chatListEmptyEl = $('wa-chat-list-empty');
    var chatCountEl = $('wa-chat-count');
    var shellEl = root.querySelector('.wa-inbox-shell');
    var bannerEl = $('wa-device-banner');
    var threadHeaderEl = $('wa-thread-header');
    var threadAvatarEl = $('wa-thread-avatar');
    var threadTitleEl = $('wa-thread-title');
    var threadSubEl = $('wa-thread-sub');
    var presencePillEl = $('wa-thread-presence-pill');
    var threadEmptyEl = $('wa-thread-empty');
    var threadBodyEl = $('wa-thread-body');
    var sendFormEl = $('wa-send-form');
    var sendInputEl = $('wa-send-input');
    var attachInputEl = $('wa-attach-input');
    var attachPreviewEl = $('wa-attach-preview');
    var pickerEl = $('wa-picker-popover');
    var pickerTitleEl = $('wa-picker-popover-title');
    var pickerBodyEl = $('wa-picker-popover-body');
    var detailPanelEl = $('wa-detail-panel');
    var detailBackdropEl = $('wa-detail-backdrop');
    var assignEl = $('wa-contact-assign-select');
    var labelPickerEl = $('wa-label-picker');
    var savePopoverEl = $('wa-save-phonebook-popover');
    var lightboxEl = $('wa-media-lightbox-overlay');
    var lightboxContentEl = $('wa-media-lightbox-content');
    var newChatEl = $('wa-new-chat-overlay');
    var newChatInputEl = $('wa-new-chat-input');
    var newChatErrorEl = $('wa-new-chat-error');

    // ---------- format ----------
    function timeLabel(iso) {
        return iso ? new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '';
    }

    function dayKey(iso) {
        var d = new Date(iso);
        return d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate();
    }

    function dateGroupLabel(iso) {
        if (!iso) return 'Lainnya';
        var d = new Date(iso);
        var now = new Date();
        var start = function (x) { return new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime(); };
        var diff = Math.round((start(now) - start(d)) / 86400000);
        if (diff === 0) return 'Hari Ini';
        if (diff === 1) return 'Kemarin';
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: d.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
    }

    var AVATAR_COLORS = ['#F97316', '#14B8A6', '#8B5CF6', '#3B82F6', '#EC4899', '#F59E0B', '#10B981', '#6366F1'];

    function chatLabel(chat) {
        return chat.name || (chat.phone ? '+' + chat.phone : (chat.is_group ? 'Grup' : chat.chat_jid.split('@')[0]));
    }

    function applyAvatar(container, chat, size) {
        container.innerHTML = '';
        container.style.width = size + 'px';
        container.style.height = size + 'px';
        var label = chatLabel(chat);

        function fallback() {
            container.innerHTML = '';
            var hash = 0;
            for (var i = 0; i < label.length; i++) hash = (hash * 31 + label.charCodeAt(i)) >>> 0;
            container.style.background = AVATAR_COLORS[hash % AVATAR_COLORS.length];
            var letter = (label.match(/[A-Za-z]/) || [''])[0].toUpperCase();
            if (letter) {
                container.style.fontSize = Math.max(12, Math.round(size * 0.4)) + 'px';
                container.textContent = letter;
            } else {
                var icon = el('i', chat.is_group ? 'ri-group-fill' : 'ri-user-3-fill');
                icon.style.fontSize = Math.max(14, Math.round(size * 0.5)) + 'px';
                container.appendChild(icon);
            }
        }

        if (!chat.id) return fallback();
        container.style.background = 'transparent';
        var img = el('img');
        img.src = chat.avatar_url || u('avatar', chat.id);
        img.alt = '';
        img.loading = 'lazy';
        img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%';
        img.addEventListener('error', fallback);
        container.appendChild(img);
    }

    // ---------- chat list ----------
    var nodes = new Map();

    function makeNode() {
        var refs = { item: el('button', 'wa-chat-item'), avatar: el('div', 'wa-avatar-circle'), name: el('span', 'wa-chat-item-name'), time: el('span', 'wa-chat-item-time'), preview: el('span', 'wa-chat-item-preview'), assignee: el('span', 'wa-chat-item-assignee d-none'), badge: el('span', 'wa-unread-badge d-none') };
        refs.item.type = 'button';
        var body = el('div', 'wa-chat-item-body');
        var top = el('div', 'wa-chat-item-top');
        var bottom = el('div', 'wa-chat-item-bottom');
        top.append(refs.name, refs.time);
        bottom.append(refs.preview, refs.assignee, refs.badge);
        body.append(top, bottom);
        refs.item.append(refs.avatar, body);
        refs.item.addEventListener('click', function () { openChat(refs.chat); });
        return refs;
    }

    function updateNode(refs, chat) {
        refs.chat = chat;
        refs.item.className = 'wa-chat-item' + (active && active.id === chat.id ? ' active' : '');
        if (refs.avatarKey !== chat.id + chatLabel(chat)) {
            applyAvatar(refs.avatar, chat, 42);
            refs.avatarKey = chat.id + chatLabel(chat);
        }
        refs.name.textContent = chatLabel(chat);
        refs.time.textContent = timeLabel(chat.last_message_at);
        refs.preview.textContent = chat.last_message || '';
        refs.assignee.textContent = chat.assignee || '';
        refs.assignee.classList.toggle('d-none', !chat.assignee);
        refs.badge.textContent = chat.unread_count;
        refs.badge.classList.toggle('d-none', !(chat.unread_count > 0));
    }

    function visibleChats() {
        var term = searchTerm.toLowerCase();
        return chats.filter(function (c) {
            if (activeTab === 'group' ? !c.is_group : (activeTab === 'chat' ? c.is_group : !(c.unread_count > 0))) return false;
            return !term || chatLabel(c).toLowerCase().indexOf(term) > -1 || (c.phone || '').indexOf(term.replace(/\D/g, '') || '~') > -1;
        });
    }

    function renderChatList() {
        var list = visibleChats();
        chatCountEl.textContent = '(' + list.length + ')';
        var sig = list.map(function (c) { return c.id + c.last_message_at + c.unread_count + c.last_message + c.name + c.assignee; }).join('|') + (active ? active.id : '') + activeTab + searchTerm;
        if (sig === renderedSig) return;
        renderedSig = sig;

        chatListEl.innerHTML = '';
        if (!list.length) {
            chatListEmptyEl.textContent = searchTerm ? 'Tidak ada percakapan yang cocok.' : (activeTab === 'unread' ? 'Semua chat sudah dibaca.' : 'Belum ada percakapan.');
            chatListEl.appendChild(chatListEmptyEl);
            return;
        }

        var frag = document.createDocumentFragment();
        var seen = new Set();
        var lastGroup = null;
        list.forEach(function (chat) {
            var group = dateGroupLabel(chat.last_message_at);
            if (group !== lastGroup) {
                lastGroup = group;
                frag.appendChild(el('div', 'wa-date-divider', group));
            }
            seen.add(chat.id);
            var refs = nodes.get(chat.id) || makeNode();
            nodes.set(chat.id, refs);
            updateNode(refs, chat);
            frag.appendChild(refs.item);
        });
        nodes.forEach(function (_, id) { if (!seen.has(id)) nodes.delete(id); });
        chatListEl.appendChild(frag);
    }

    function setChats(list) {
        chats = list;
        chatIndex.clear();
        chats.forEach(function (c) { chatIndex.set(c.id, c); });
        renderedSig = '';
        renderChatList();
    }

    function sortChats() {
        chats.sort(function (a, b) { return (Date.parse(b.last_message_at) || 0) - (Date.parse(a.last_message_at) || 0); });
    }

    function loadChats() {
        return api(U.chats).then(function (res) {
            if (!res.ok) return toast(res.message, 'error');
            setChats(res.data.chats || []);
            var deepLink = new URLSearchParams(location.search).get('chat');
            if (deepLink && chatIndex.has(deepLink) && !active) openChat(chatIndex.get(deepLink));
        });
    }

    var reloadTimer = null;
    function reloadChatsSoon() {
        clearTimeout(reloadTimer);
        reloadTimer = setTimeout(loadChats, 600);
    }

    // ---------- messages ----------
    function parseOptions(m) {
        if (m.poll_options) return m.poll_options;
        try { return m.meta ? JSON.parse(m.meta).options : null; } catch (e) { return null; }
    }

    function norm(m) {
        return Object.assign({}, m, {
            body: m.body === undefined ? null : m.body,
            media_url: m.media_url || (m.has_media && m.id ? u('media', null, m.id) : null),
            poll_options: parseOptions(m),
        });
    }

    function ackIcon(status) {
        var map = { pending: 'ri-time-line', sent: 'ri-check-line', delivered: 'ri-check-double-line', read: 'ri-check-double-line wa-msg-ack-read', played: 'ri-check-double-line wa-msg-ack-read', failed: 'ri-error-warning-line text-danger' };
        return el('i', 'wa-msg-ack ' + (map[status] || map.sent));
    }

    function mediaNode(msg) {
        var url = msg.media_url;
        if (!url) return null;
        if (msg.type === 'image' || msg.type === 'sticker') {
            var img = el('img', msg.type === 'sticker' ? 'wa-msg-sticker' : 'wa-msg-image');
            img.src = url;
            img.loading = 'lazy';
            img.alt = msg.media_name || 'Gambar';
            img.style.cursor = 'zoom-in';
            img.addEventListener('click', function () { openLightbox(url, 'image'); });
            img.addEventListener('error', function () {
                var retry = el('div', 'wa-msg-media-missing');
                retry.append(el('i', 'ri-image-line'), el('span', null, 'Foto belum bisa dimuat, ketuk untuk coba lagi'));
                retry.addEventListener('click', function () { retry.replaceWith(mediaNode(Object.assign({}, msg, { media_url: url + (url.indexOf('?') > -1 ? '&' : '?') + 't=' + Date.now() }))); });
                img.replaceWith(retry);
            }, { once: true });
            return img;
        }
        if (msg.type === 'video' || msg.type === 'audio') {
            var player = el(msg.type, msg.type === 'video' ? 'wa-msg-video' : 'wa-msg-audio');
            player.src = url;
            player.controls = true;
            player.preload = 'metadata';
            return player;
        }
        var link = el('a', 'wa-msg-document');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.append(el('i', 'ri-file-3-line'), el('span', null, msg.media_name || 'Dokumen'));
        return link;
    }

    function bubble(msg) {
        var row = el('div', 'wa-msg-row' + (msg.from_me ? ' from-me' : ''));
        var box = el('div', 'wa-msg-bubble');
        if (msg.id) row.dataset.id = msg.id;
        if (msg.wa_id) row.dataset.waId = msg.wa_id;

        if (active && active.is_group && !msg.from_me && msg.sender_name) box.appendChild(el('div', 'wa-msg-sender', msg.sender_name));

        if (msg.type === 'revoked') {
            box.appendChild(el('div', 'wa-msg-revoked', 'Pesan ini telah dihapus'));
        } else if (msg.type === 'poll') {
            var q = el('div', 'wa-msg-poll-question');
            q.append(el('i', 'ri-bar-chart-box-line'), el('span', null, msg.body || ''));
            box.appendChild(q);
            (msg.poll_options || []).forEach(function (opt) { box.appendChild(el('div', 'wa-msg-poll-option', opt)); });
            if (msg.from_me && msg.id) {
                var btn = el('button', 'btn btn-sm btn-light wa-msg-poll-results-btn');
                btn.type = 'button';
                btn.append(el('i', 'ri-pie-chart-line'), document.createTextNode(' Lihat Hasil'));
                btn.addEventListener('click', function () { showPollResults(msg.id); });
                box.appendChild(btn);
            }
        } else {
            var media = mediaNode(msg);
            if (media) {
                box.classList.add('wa-msg-bubble-media');
                if (msg.type === 'sticker') box.classList.add('wa-msg-bubble-sticker');
                box.appendChild(media);
            }
            if (msg.body || !media) {
                var text = el('div', 'wa-msg-text', msg.body || (msg.type === 'reaction' ? '' : (media ? '' : '[' + msg.type + ']')));
                text.style.whiteSpace = 'pre-wrap';
                text.style.wordBreak = 'break-word';
                box.appendChild(text);
            }
        }

        var time = el('div', 'wa-msg-time');
        time.appendChild(el('span', null, timeLabel(msg.sent_at)));
        if (msg.from_me) time.appendChild(ackIcon(msg.status));
        box.appendChild(time);
        row.appendChild(box);
        return row;
    }

    function divider(iso) {
        var wrap = el('div', 'wa-msg-divider');
        wrap.appendChild(el('span', null, dateGroupLabel(iso)));
        return wrap;
    }

    function renderThread(keepOffsetFromBottom) {
        var fromBottom = threadBodyEl.scrollHeight - threadBodyEl.scrollTop;
        threadBodyEl.innerHTML = '';
        if (hasMore) {
            var more = el('div', 'wa-load-older');
            var btn = el('button', null, 'Muat pesan sebelumnya');
            btn.type = 'button';
            btn.addEventListener('click', loadOlder);
            more.appendChild(btn);
            threadBodyEl.appendChild(more);
        }
        var lastDay = null;
        threadMsgs.forEach(function (m) {
            if (dayKey(m.sent_at) !== lastDay) {
                lastDay = dayKey(m.sent_at);
                threadBodyEl.appendChild(divider(m.sent_at));
            }
            threadBodyEl.appendChild(bubble(m));
        });
        threadBodyEl.dataset.lastDay = lastDay || '';
        threadBodyEl.scrollTop = keepOffsetFromBottom ? threadBodyEl.scrollHeight - fromBottom : threadBodyEl.scrollHeight;
    }

    function appendMessage(msg, rowEl) {
        var atBottom = threadBodyEl.scrollTop + threadBodyEl.clientHeight >= threadBodyEl.scrollHeight - 40;
        if (dayKey(msg.sent_at) !== threadBodyEl.dataset.lastDay) {
            threadBodyEl.dataset.lastDay = dayKey(msg.sent_at);
            threadBodyEl.appendChild(divider(msg.sent_at));
        }
        threadBodyEl.appendChild(rowEl || bubble(msg));
        if (atBottom || msg.from_me) threadBodyEl.scrollTop = threadBodyEl.scrollHeight;
    }

    function loadMessages() {
        var chatId = active.id;
        threadBodyEl.innerHTML = '<div class="wa-thread-loading"><i class="ri-loader-4-line"></i></div>';
        api(u('messages', chatId)).then(function (res) {
            if (!active || active.id !== chatId) return;
            if (!res.ok) return toast(res.message, 'error');
            threadMsgs = (res.data.messages || []).map(norm);
            hasMore = res.data.has_more;
            renderThread(false);
        });
    }

    function loadOlder() {
        if (!active || !active.id || !hasMore || loadingOlder || !threadMsgs.length) return;
        loadingOlder = true;
        var chatId = active.id;
        api(u('messages', chatId) + '?before=' + encodeURIComponent(threadMsgs[0].id)).then(function (res) {
            loadingOlder = false;
            if (!active || active.id !== chatId || !res.ok) return;
            threadMsgs = (res.data.messages || []).map(norm).concat(threadMsgs);
            hasMore = res.data.has_more;
            renderThread(true);
        });
    }

    threadBodyEl.addEventListener('scroll', function () { if (threadBodyEl.scrollTop < 40) loadOlder(); });

    // ---------- open chat ----------
    function openChat(chat) {
        active = chat;
        threadMsgs = [];
        hasMore = false;
        clearAttach();
        closeSearch();
        closePicker();
        shellEl.classList.add('wa-mobile-chat-open');
        threadEmptyEl.classList.add('d-none');
        threadHeaderEl.classList.remove('d-none');
        threadHeaderEl.style.display = 'flex';
        threadBodyEl.classList.remove('d-none');
        sendFormEl.classList.remove('d-none');
        threadTitleEl.textContent = chatLabel(chat);
        threadSubEl.textContent = chat.is_group ? 'Grup' : (chat.phone ? '+' + chat.phone : '');
        renderPresence({ state: chat.is_group ? 'group' : 'unknown' });
        applyAvatar(threadAvatarEl, chat, 40);
        renderedSig = '';
        renderChatList();
        renderDetailHeader(chat);
        labelPickerEl.classList.add('d-none');
        savePopoverEl.classList.add('d-none');
        hideNoteForm();

        if (!chat.id) {
            threadBodyEl.innerHTML = '';
            resetDetail();
            sendInputEl.focus();
            return;
        }
        loadMessages();
        setTimeout(function () {
            if (!active || active.id !== chat.id) return;
            loadDetail();
            selectMediaTab('image');
            markRead();
            if (!chat.is_group) api(u('presence', chat.id), { method: 'POST' });
        }, 0);
    }

    var readTimer = null;
    function markRead() {
        if (!active || !active.id || document.visibilityState !== 'visible') return;
        clearTimeout(readTimer);
        var chatId = active.id;
        readTimer = setTimeout(function () {
            var chat = chatIndex.get(chatId);
            if (chat && chat.unread_count > 0) {
                chat.unread_count = 0;
                renderedSig = '';
                renderChatList();
            }
            api(u('read', chatId), { method: 'POST' });
        }, 700);
    }

    document.addEventListener('visibilitychange', markRead);

    // ---------- presence ----------
    var typingTimer = null;
    function renderPresence(p) {
        var states = {
            typing: ['mengetik...', 'Mengetik', 'wa-pill-typing'],
            online: ['online', 'Online', 'wa-pill-online'],
            offline: [p.last_seen ? 'terakhir dilihat ' + dateGroupLabel(p.last_seen).toLowerCase() + ' ' + timeLabel(p.last_seen) : '', 'Offline', 'wa-pill-offline'],
            group: [null, 'Grup', 'wa-pill-offline'],
            unknown: [null, 'Status tidak diketahui', 'wa-pill-offline'],
        };
        var s = states[p.state] || states.unknown;
        if (s[0] !== null) threadSubEl.textContent = s[0] || (active && active.phone ? '+' + active.phone : '');
        presencePillEl.textContent = s[1];
        presencePillEl.className = 'wa-presence-pill ' + s[2];
        clearTimeout(typingTimer);
        if (p.state === 'typing') typingTimer = setTimeout(function () { renderPresence({ state: 'online' }); }, 6000);
    }

    // ---------- sending ----------
    function target(extra) {
        return Object.assign(active.id ? { chat_id: active.id } : { phone: active.phone }, extra);
    }

    function addPending(ref, msg, retry) {
        var row = bubble(msg);
        row.classList.add('wa-msg-optimistic');
        appendMessage(msg, row);
        pending.set(ref, { row: row, retry: retry });
    }

    function failPending(ref, message) {
        var item = pending.get(ref);
        if (!item) return;
        pending.delete(ref);
        item.row.classList.remove('wa-msg-optimistic');
        item.row.classList.add('wa-msg-failed');
        var ack = item.row.querySelector('.wa-msg-ack');
        if (ack) ack.replaceWith(ackIcon('failed'));
        var note = el('div', 'wa-msg-failed-note');
        note.append(el('span', null, message || 'Gagal terkirim.'));
        if (item.retry) {
            var btn = el('button', null, 'Kirim ulang');
            btn.type = 'button';
            btn.addEventListener('click', function () { item.row.remove(); item.retry(); });
            note.appendChild(btn);
        }
        item.row.querySelector('.wa-msg-bubble').appendChild(note);
    }

    function trackSend(ref, request) {
        request.then(function (res) {
            if (!res.ok) return failPending(ref, res.message);
            if (res.data.data && res.data.data.outbox_id) pendingByOutbox.set(res.data.data.outbox_id, ref);
        });
    }

    function sendText(text) {
        var ref = BizWa.uuid();
        addPending(ref, { from_me: true, type: 'text', body: text, status: 'pending', sent_at: new Date().toISOString() }, function () { sendText(text); });
        trackSend(ref, api(U.send, { method: 'POST', json: target({ text: text, client_ref: ref }) }));
    }

    function sendFile(file, caption) {
        var ref = BizWa.uuid();
        var form = new FormData();
        form.append('file', file);
        if (caption) form.append('caption', caption);
        form.append('client_ref', ref);
        var t = target({});
        Object.keys(t).forEach(function (k) { form.append(k, t[k]); });
        addPending(ref, { from_me: true, type: 'text', body: '📎 ' + file.name + (caption ? '\n' + caption : '') + '\n(mengunggah...)', status: 'pending', sent_at: new Date().toISOString() }, function () { sendFile(file, caption); });
        trackSend(ref, api(U.sendMedia, { method: 'POST', body: form }));
    }

    sendFormEl.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!active) return;
        var text = sendInputEl.value.trim();
        if (pendingFile) {
            sendFile(pendingFile, text);
            clearAttach();
        } else if (text) {
            sendText(text);
        } else {
            return;
        }
        sendInputEl.value = '';
        sendInputEl.style.height = 'auto';
        sendInputEl.focus();
    });

    sendInputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendFormEl.requestSubmit ? sendFormEl.requestSubmit() : sendFormEl.dispatchEvent(new Event('submit', { cancelable: true }));
        } else if (e.key === '/' && sendInputEl.value === '') {
            e.preventDefault();
            openQuickReplies();
        }
    });

    function autoGrow() {
        sendInputEl.style.height = 'auto';
        sendInputEl.style.height = Math.min(sendInputEl.scrollHeight, 120) + 'px';
    }
    sendInputEl.addEventListener('input', autoGrow);

    function insertAtCursor(text) {
        var start = sendInputEl.selectionStart || sendInputEl.value.length;
        var end = sendInputEl.selectionEnd || sendInputEl.value.length;
        sendInputEl.value = sendInputEl.value.slice(0, start) + text + sendInputEl.value.slice(end);
        sendInputEl.focus();
        sendInputEl.setSelectionRange(start + text.length, start + text.length);
        autoGrow();
    }

    // ---------- attachments ----------
    $('wa-attach-btn').addEventListener('click', function () { attachInputEl.click(); });
    attachInputEl.addEventListener('change', function () {
        var file = attachInputEl.files && attachInputEl.files[0];
        if (!file) return;
        if (file.size > 64 * 1024 * 1024) {
            attachInputEl.value = '';
            return toast('File terlalu besar (maksimal 64 MB).', 'warning');
        }
        pendingFile = file;
        $('wa-attach-preview-icon').className = file.type.indexOf('image/') === 0 ? 'ri-image-line' : (file.type.indexOf('video/') === 0 ? 'ri-video-line' : (file.type.indexOf('audio/') === 0 ? 'ri-mic-line' : 'ri-file-3-line'));
        $('wa-attach-preview-name').textContent = file.name;
        attachPreviewEl.classList.add('show');
        sendInputEl.placeholder = 'Tambahkan keterangan (opsional)...';
        sendInputEl.focus();
    });
    $('wa-attach-preview-cancel').addEventListener('click', clearAttach);

    function clearAttach() {
        pendingFile = null;
        attachInputEl.value = '';
        attachPreviewEl.classList.remove('show');
        sendInputEl.placeholder = 'Ketik pesan... ( / untuk balasan cepat)';
    }

    // ---------- pickers (emoji & balasan cepat) ----------
    var EMOJIS = ['😀','😁','😂','🤣','😊','😍','😘','😉','😎','🤔','😅','😢','😭','😡','👍','👎','🙏','👏','💪','🔥','🎉','❤️','💯','✅','❌','⚠️','📌','⏰','📞','📎','😴','🥳','🤗','😇','🙌','👋','🤝','💬','📷','🎁'];

    function openPicker(title) {
        pickerTitleEl.textContent = title;
        pickerBodyEl.innerHTML = '';
        pickerEl.classList.remove('d-none');
    }

    function closePicker() {
        pickerEl.classList.add('d-none');
        pickerBodyEl.innerHTML = '';
    }

    function togglePicker(title, open) {
        return function () { !pickerEl.classList.contains('d-none') && pickerTitleEl.textContent === title ? closePicker() : open(); };
    }

    function openEmoji() {
        openPicker('Emoji');
        var grid = el('div', 'wa-emoji-grid');
        EMOJIS.forEach(function (emoji) {
            var b = el('button', null, emoji);
            b.type = 'button';
            b.addEventListener('click', function () { insertAtCursor(emoji); });
            grid.appendChild(b);
        });
        pickerBodyEl.appendChild(grid);
    }

    function openQuickReplies() {
        openPicker('Balasan Cepat');
        pickerBodyEl.appendChild(el('div', 'wa-picker-empty', 'Memuat...'));
        api(U.quickReplies).then(function (res) {
            if (pickerTitleEl.textContent !== 'Balasan Cepat') return;
            pickerBodyEl.innerHTML = '';
            var items = res.ok ? res.data.quick_replies : null;
            if (!items || !items.length) {
                var empty = el('div', 'wa-picker-empty', items ? 'Belum ada balasan cepat. ' : 'Gagal memuat balasan cepat. Coba lagi.');
                if (items) {
                    var link = el('a', null, 'Buat sekarang');
                    link.href = U.quickRepliesPage;
                    link.target = '_blank';
                    empty.appendChild(link);
                }
                return pickerBodyEl.appendChild(empty);
            }
            items.forEach(function (item) {
                var b = el('button', 'wa-picker-list-item');
                b.type = 'button';
                var title = el('div', 'wa-picker-list-title');
                title.appendChild(el('span', null, item.title));
                if (item.shortcut) title.appendChild(el('span', 'wa-picker-shortcut-tag', '/' + item.shortcut));
                b.append(title, el('div', 'wa-picker-list-preview', item.body));
                b.addEventListener('click', function () { insertAtCursor(item.body); closePicker(); });
                pickerBodyEl.appendChild(b);
            });
        });
    }

    $('wa-emoji-btn').addEventListener('click', togglePicker('Emoji', openEmoji));
    $('wa-quick-reply-btn').addEventListener('click', togglePicker('Balasan Cepat', openQuickReplies));
    $('wa-picker-popover-close').addEventListener('click', closePicker);
    document.addEventListener('click', function (e) {
        if (pickerEl.classList.contains('d-none') || pickerEl.contains(e.target) || e.target.closest('#wa-emoji-btn, #wa-quick-reply-btn')) return;
        closePicker();
    });

    // ---------- polling ----------
    var pollModalEl = $('wa-poll-modal');
    var pollListEl = $('wa-poll-options-list');
    var pollErrorEl = $('wa-poll-error');

    function addPollOption() {
        if (pollListEl.children.length >= 12) return;
        var row = el('div', 'd-flex gap-2 align-items-center');
        var input = el('input', 'form-control form-control-sm');
        input.maxLength = 100;
        input.placeholder = 'Pilihan jawaban';
        var remove = el('button', 'btn btn-sm btn-light text-danger');
        remove.type = 'button';
        remove.appendChild(el('i', 'ri-close-line'));
        remove.addEventListener('click', function () { if (pollListEl.children.length > 2) row.remove(); });
        row.append(input, remove);
        pollListEl.appendChild(row);
    }

    pollModalEl.addEventListener('show.bs.modal', function () {
        $('wa-poll-question').value = '';
        $('wa-poll-multi').checked = false;
        pollListEl.innerHTML = '';
        addPollOption();
        addPollOption();
        pollErrorEl.classList.add('d-none');
    });
    $('wa-poll-option-add').addEventListener('click', addPollOption);

    $('wa-poll-send-btn').addEventListener('click', function () {
        if (!active) return;
        var question = $('wa-poll-question').value.trim();
        var options = [].map.call(pollListEl.querySelectorAll('input'), function (i) { return i.value.trim(); }).filter(Boolean);
        var error = !question ? 'Pertanyaan wajib diisi.' : (options.length < 2 ? 'Minimal 2 pilihan jawaban.' : (new Set(options).size !== options.length ? 'Pilihan jawaban tidak boleh sama.' : ''));
        if (error) {
            pollErrorEl.textContent = error;
            return pollErrorEl.classList.remove('d-none');
        }
        bootstrap.Modal.getOrCreateInstance(pollModalEl).hide();
        var send = function () {
            var ref = BizWa.uuid();
            addPending(ref, { from_me: true, type: 'poll', body: question, poll_options: options, status: 'pending', sent_at: new Date().toISOString() }, send);
            trackSend(ref, api(U.sendPoll, { method: 'POST', json: target({ question: question, options: options, selectable_count: $('wa-poll-multi').checked ? options.length : 1, client_ref: ref }) }));
        };
        send();
    });

    var pollShown = null;
    function showPollResults(messageId) {
        var modalEl = $('wa-poll-results-modal');
        var body = $('wa-poll-results-body');
        pollShown = messageId;
        if (!modalEl.classList.contains('show')) {
            body.innerHTML = '<div class="text-muted text-center py-3">Memuat...</div>';
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
        api(u('poll', null, messageId)).then(function (res) {
            if (!res.ok) { body.textContent = res.message; return; }
            $('wa-poll-results-question').textContent = res.data.question || '';
            body.innerHTML = '';
            var tally = res.data.tally || {};
            var max = Math.max.apply(null, [1].concat(Object.values(tally)));
            Object.keys(tally).forEach(function (opt) {
                var row = el('div', 'mb-2');
                var head = el('div', 'd-flex justify-content-between small mb-1');
                head.append(el('span', null, opt), el('span', 'text-muted', tally[opt]));
                var bar = el('div', 'progress');
                bar.style.height = '8px';
                var fill = el('div', 'progress-bar bg-success');
                fill.style.width = Math.round(tally[opt] / max * 100) + '%';
                bar.appendChild(fill);
                row.append(head, bar);
                body.appendChild(row);
            });
            $('wa-poll-results-count').textContent = res.data.voters + ' orang telah menjawab.';
        });
    }
    $('wa-poll-results-modal').addEventListener('hidden.bs.modal', function () { pollShown = null; });

    // ---------- detail panel ----------
    function renderDetailHeader(chat) {
        $('wa-detail-empty').classList.add('d-none');
        $('wa-detail-content').classList.remove('d-none');
        applyAvatar($('wa-detail-avatar'), chat, 64);
        $('wa-detail-name').textContent = chatLabel(chat);
        $('wa-detail-phone').textContent = chat.is_group ? 'Grup WhatsApp' : (chat.phone ? '+' + chat.phone : 'Nomor belum teridentifikasi');
        var personal = !chat.is_group && chat.phone && chat.id;
        var vcard = $('wa-detail-save-contact');
        vcard.classList.toggle('d-none', !personal);
        if (personal) vcard.href = u('vcard', chat.id);
        $('wa-detail-save-phonebook-btn').classList.add('d-none');
        $('wa-detail-contact').classList.add('d-none');
    }

    function resetDetail() {
        detail = { labels: [], notes: [], team: [], groups: [], contact: null, assigned: null, botPaused: null };
        renderAssign();
        renderBot();
        renderLabels();
        renderNotes();
        $('wa-media-grid').innerHTML = '';
        $('wa-media-empty').classList.remove('d-none');
    }

    function loadDetail() {
        var chatId = active.id;
        api(u('detail', chatId)).then(function (res) {
            if (!active || active.id !== chatId || !res.ok) return;
            detail = { labels: res.data.labels, notes: res.data.notes, team: res.data.team, groups: res.data.groups, contact: res.data.contact, assigned: res.data.assigned_user_id, botPaused: res.data.bot_paused_until };
            renderAssign();
            renderBot();
            renderLabels();
            renderNotes();
            renderContact();
        });
    }

    function renderContact() {
        var info = $('wa-detail-contact');
        var btn = $('wa-detail-save-phonebook-btn');
        var personal = active && active.id && !active.is_group && active.phone;
        info.classList.toggle('d-none', !detail.contact);
        if (detail.contact) info.textContent = 'Di Buku Kontak: ' + detail.contact.name + (detail.contact.groups.length ? ' · ' + detail.contact.groups.join(', ') : '');
        btn.classList.toggle('d-none', !personal || !!detail.contact);
        var select = $('wa-save-phonebook-category');
        select.length = 1;
        detail.groups.forEach(function (g) { select.appendChild(new Option(g.name, g.id)); });
    }

    function renderAssign() {
        assignEl.innerHTML = '';
        assignEl.appendChild(new Option('Belum ditugaskan', ''));
        detail.team.forEach(function (m) { assignEl.appendChild(new Option(m.name, m.id, false, m.id === detail.assigned)); });
        assignEl.disabled = !active || !active.id;
    }

    var botEl = $('wa-bot-select');
    function renderBot() {
        var status = $('wa-bot-status');
        botEl.value = '0';
        botEl.disabled = !active || !active.id || active.is_group;
        status.classList.toggle('d-none', !detail.botPaused);
        status.textContent = detail.botPaused ? 'Bot dijeda sampai ' + detail.botPaused + '. Bot juga otomatis diam bila chat ditugaskan atau admin baru membalas.' : '';
    }

    botEl.addEventListener('change', function () {
        api(u('bot', active.id), { method: 'POST', json: { hours: parseInt(botEl.value, 10) } }).then(function (res) {
            toast(res.ok ? res.data.message : res.message, res.ok ? 'success' : 'error');
            if (res.ok) detail.botPaused = res.data.bot_paused_until;
            renderBot();
        });
    });

    assignEl.addEventListener('change', function () {
        var chatId = active.id;
        api(u('assign', chatId), { method: 'POST', json: { user_id: assignEl.value || null } }).then(function (res) {
            toast(res.ok ? res.data.message : res.message, res.ok ? 'success' : 'error');
            if (!res.ok) return;
            var chat = chatIndex.get(chatId);
            var member = detail.team.find(function (m) { return m.id === assignEl.value; });
            if (chat) { chat.assignee = member ? member.name : null; renderedSig = ''; renderChatList(); }
        });
    });

    function renderLabels() {
        var chips = $('wa-label-chips');
        var picker = $('wa-label-picker-list');
        chips.innerHTML = '';
        picker.innerHTML = '';
        var assigned = detail.labels.filter(function (l) { return l.assigned; });
        $('wa-label-empty').classList.toggle('d-none', assigned.length > 0);
        assigned.forEach(function (label) {
            var chip = el('span', 'wa-label-chip');
            chip.style.background = label.color;
            var x = el('button', null, '×');
            x.type = 'button';
            x.title = 'Lepas label';
            x.addEventListener('click', function () { toggleLabel(label); });
            chip.append(el('span', null, label.name), x);
            chips.appendChild(chip);
        });
        if (!detail.labels.length) picker.appendChild(el('div', 'wa-label-picker-empty', 'Belum ada label.'));
        detail.labels.forEach(function (label) {
            var item = el('div', 'wa-label-picker-item');
            var dot = el('span', 'wa-label-dot');
            dot.style.background = label.color;
            item.append(dot, el('span', null, label.name));
            if (label.assigned) item.appendChild(el('i', 'ri-check-line'));
            item.addEventListener('click', function () { toggleLabel(label); });
            picker.appendChild(item);
        });
        var manage = el('a', 'wa-label-picker-manage');
        manage.href = U.labelsPage;
        manage.target = '_blank';
        manage.append(el('i', 'ri-settings-3-line'), document.createTextNode(' Kelola label'));
        picker.appendChild(manage);
    }

    function toggleLabel(label) {
        api(u('labelAttach', active.id, label.id), { method: label.assigned ? 'DELETE' : 'POST' }).then(function (res) {
            if (!res.ok) return toast(res.message, 'error');
            detail.labels = res.data.labels;
            renderLabels();
        });
    }

    $('wa-label-add-btn').addEventListener('click', function (e) {
        e.stopPropagation();
        if (active && active.id) labelPickerEl.classList.toggle('d-none');
    });
    document.addEventListener('click', function (e) {
        if (!labelPickerEl.classList.contains('d-none') && !labelPickerEl.contains(e.target) && e.target.id !== 'wa-label-add-btn') labelPickerEl.classList.add('d-none');
    });

    // Notes
    var editingNote = null;
    var noteInputEl = $('wa-note-input');

    function renderNotes() {
        var list = $('wa-note-list');
        list.innerHTML = '';
        $('wa-note-empty').classList.toggle('d-none', detail.notes.length > 0);
        detail.notes.forEach(function (note) {
            var item = el('div', 'wa-note-item');
            var meta = el('div', 'wa-note-item-meta');
            var actions = el('span', 'wa-note-item-actions');
            var edit = el('button');
            edit.type = 'button';
            edit.title = 'Ubah catatan';
            edit.appendChild(el('i', 'ri-pencil-line'));
            edit.addEventListener('click', function () { editingNote = note.id; noteInputEl.value = note.note; $('wa-note-save').textContent = 'Update'; showNoteForm(); });
            var del = el('button', 'wa-note-delete-btn');
            del.type = 'button';
            del.title = 'Hapus catatan';
            del.appendChild(el('i', 'ri-delete-bin-line'));
            del.addEventListener('click', function () {
                if (confirm('Hapus catatan ini?')) noteRequest(u('note', active.id, note.id), 'DELETE');
            });
            actions.append(edit, del);
            meta.append(el('span', null, (note.author ? note.author + ' · ' : '') + new Date(note.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })), actions);
            item.append(el('div', 'wa-note-item-text', note.note), meta);
            list.appendChild(item);
        });
    }

    function noteRequest(url, method, json) {
        return api(url, { method: method, json: json }).then(function (res) {
            if (!res.ok) return toast(res.message, 'error');
            detail.notes = res.data.notes;
            renderNotes();
            hideNoteForm();
        });
    }

    function showNoteForm() { $('wa-note-form').classList.remove('d-none'); noteInputEl.focus(); }
    function hideNoteForm() { $('wa-note-form').classList.add('d-none'); noteInputEl.value = ''; editingNote = null; $('wa-note-save').textContent = 'Simpan'; }

    $('wa-note-add-btn').addEventListener('click', function () {
        if (!active || !active.id) return;
        $('wa-note-form').classList.contains('d-none') ? showNoteForm() : hideNoteForm();
    });
    $('wa-note-cancel').addEventListener('click', hideNoteForm);
    $('wa-note-save').addEventListener('click', function () {
        var text = noteInputEl.value.trim();
        if (!text || !active || !active.id) return;
        editingNote ? noteRequest(u('note', active.id, editingNote), 'PUT', { note: text }) : noteRequest(u('notes', active.id), 'POST', { note: text });
    });

    // Simpan ke Buku Kontak
    $('wa-detail-save-phonebook-btn').addEventListener('click', function () {
        $('wa-save-phonebook-name').value = active.name || '';
        $('wa-save-phonebook-error').classList.add('d-none');
        savePopoverEl.classList.toggle('d-none');
    });
    $('wa-save-phonebook-cancel-btn').addEventListener('click', function () { savePopoverEl.classList.add('d-none'); });
    $('wa-save-phonebook-confirm-btn').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        api(u('contact', active.id), { method: 'POST', json: { name: $('wa-save-phonebook-name').value.trim() || null, group_id: $('wa-save-phonebook-category').value || null } }).then(function (res) {
            btn.disabled = false;
            if (!res.ok) {
                $('wa-save-phonebook-error').textContent = res.message;
                return $('wa-save-phonebook-error').classList.remove('d-none');
            }
            savePopoverEl.classList.add('d-none');
            detail.contact = res.data.contact;
            renderContact();
            toast(res.data.message, 'success');
        });
    });

    // Media & files
    function selectMediaTab(type) {
        mediaType = type;
        document.querySelectorAll('#wa-media-tabs .wa-media-tab').forEach(function (t) { t.classList.toggle('active', t.dataset.mediaType === type); });
        var chatId = active.id;
        var grid = $('wa-media-grid');
        api(u('mediaList', chatId) + '?type=' + type).then(function (res) {
            if (!active || active.id !== chatId || mediaType !== type) return;
            var items = res.ok ? res.data.items : [];
            grid.innerHTML = '';
            $('wa-media-empty').classList.toggle('d-none', items.length > 0);
            items.forEach(function (item) {
                var box = el('a', 'wa-media-grid-item' + (type === 'document' ? ' wa-media-doc' : ''));
                box.href = item.media_url;
                box.target = '_blank';
                box.rel = 'noopener';
                if (type === 'document') {
                    box.append(el('i', 'ri-file-3-line'), el('span', null, item.media_name || 'Dokumen'));
                } else {
                    var media = el(type === 'video' ? 'video' : 'img');
                    media.src = item.media_url;
                    if (type === 'video') media.muted = true; else media.loading = 'lazy';
                    box.appendChild(media);
                    box.addEventListener('click', function (e) { e.preventDefault(); openLightbox(item.media_url, type); });
                }
                grid.appendChild(box);
            });
        });
    }

    document.querySelectorAll('#wa-media-tabs .wa-media-tab').forEach(function (tab) {
        tab.addEventListener('click', function () { if (active && active.id) selectMediaTab(tab.dataset.mediaType); });
    });

    // Panel kanan: kolom tetap (>1200px) atau panel geser (<=1200px).
    var narrow = window.matchMedia('(max-width: 1200px)');
    $('wa-toggle-detail-btn').addEventListener('click', function () {
        if (narrow.matches) {
            var open = !detailPanelEl.classList.contains('wa-detail-open');
            detailPanelEl.classList.remove('wa-hidden');
            detailPanelEl.classList.toggle('wa-detail-open', open);
            detailBackdropEl.classList.toggle('show', open);
        } else {
            detailPanelEl.classList.toggle('wa-hidden');
        }
    });
    function closeDetailOverlay() { detailPanelEl.classList.remove('wa-detail-open'); detailBackdropEl.classList.remove('show'); }
    detailBackdropEl.addEventListener('click', closeDetailOverlay);
    $('wa-detail-close-btn').addEventListener('click', closeDetailOverlay);
    $('wa-thread-back-btn').addEventListener('click', function () { shellEl.classList.remove('wa-mobile-chat-open'); });

    // ---------- lightbox ----------
    function openLightbox(url, kind) {
        lightboxContentEl.innerHTML = '';
        var media = el(kind === 'video' ? 'video' : 'img');
        media.src = url;
        if (kind === 'video') { media.controls = true; media.autoplay = true; }
        lightboxContentEl.appendChild(media);
        lightboxEl.classList.remove('d-none');
    }
    function closeLightbox() { lightboxEl.classList.add('d-none'); lightboxContentEl.innerHTML = ''; }
    $('wa-media-lightbox-close').addEventListener('click', closeLightbox);
    lightboxEl.addEventListener('click', function (e) { if (e.target === lightboxEl) closeLightbox(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !lightboxEl.classList.contains('d-none')) closeLightbox(); });

    // ---------- search in thread ----------
    var searchBarEl = $('wa-thread-search-bar');
    var searchInputEl = $('wa-thread-search-input');
    var searchCountEl = $('wa-thread-search-count');
    var matches = [];
    var matchIndex = -1;

    function clearMatches() {
        matches.forEach(function (m) { m.classList.remove('wa-msg-highlight', 'wa-msg-highlight-active'); });
        matches = [];
        matchIndex = -1;
    }

    function goMatch(step) {
        if (!matches.length) return;
        matchIndex = (matchIndex + step + matches.length) % matches.length;
        matches.forEach(function (m) { m.classList.remove('wa-msg-highlight-active'); });
        matches[matchIndex].classList.add('wa-msg-highlight-active');
        matches[matchIndex].scrollIntoView({ block: 'center', behavior: 'smooth' });
        searchCountEl.textContent = (matchIndex + 1) + ' / ' + matches.length;
    }

    function closeSearch() {
        searchBarEl.classList.add('d-none');
        searchInputEl.value = '';
        searchCountEl.textContent = '';
        clearMatches();
    }

    $('wa-thread-search-btn').addEventListener('click', function () {
        if (!searchBarEl.classList.contains('d-none')) return closeSearch();
        searchBarEl.classList.remove('d-none');
        setTimeout(function () { searchInputEl.focus(); }, 0);
    });
    $('wa-thread-search-close').addEventListener('click', closeSearch);
    $('wa-thread-search-next').addEventListener('click', function () { goMatch(1); });
    $('wa-thread-search-prev').addEventListener('click', function () { goMatch(-1); });
    searchInputEl.addEventListener('input', function () {
        clearMatches();
        var term = searchInputEl.value.trim().toLowerCase();
        searchCountEl.textContent = '';
        if (!term) return;
        threadBodyEl.querySelectorAll('.wa-msg-bubble').forEach(function (b) {
            if (b.textContent.toLowerCase().indexOf(term) > -1) {
                b.classList.add('wa-msg-highlight');
                matches.push(b);
            }
        });
        matches.length ? goMatch(1) : (searchCountEl.textContent = 'Tidak ditemukan');
    });
    searchInputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); goMatch(e.shiftKey ? -1 : 1); }
        if (e.key === 'Escape') closeSearch();
    });

    // ---------- list controls & new chat ----------
    $('wa-search-input').addEventListener('input', function (e) { searchTerm = e.target.value.trim(); renderChatList(); });
    $('wa-chat-tabs').addEventListener('click', function (e) {
        var btn = e.target.closest('.wa-chat-tab');
        if (!btn) return;
        document.querySelectorAll('#wa-chat-tabs .wa-chat-tab').forEach(function (b) { b.classList.toggle('active', b === btn); });
        activeTab = btn.dataset.tab;
        renderChatList();
    });

    function closeNewChat() { newChatEl.classList.add('d-none'); }
    $('wa-new-chat-btn').addEventListener('click', function () {
        newChatInputEl.value = '';
        newChatErrorEl.classList.add('d-none');
        newChatEl.classList.remove('d-none');
        setTimeout(function () { newChatInputEl.focus(); }, 0);
    });
    $('wa-new-chat-close').addEventListener('click', closeNewChat);
    $('wa-new-chat-cancel').addEventListener('click', closeNewChat);
    newChatEl.addEventListener('click', function (e) { if (e.target === newChatEl) closeNewChat(); });

    function startNewChat() {
        var digits = newChatInputEl.value.replace(/\D/g, '').replace(/^0/, '62');
        if (digits.indexOf('62') !== 0 && digits.length <= 12) digits = '62' + digits;
        if (digits.length < 10 || digits.length > 15) {
            newChatErrorEl.textContent = 'Masukkan nomor WhatsApp yang valid.';
            return newChatErrorEl.classList.remove('d-none');
        }
        closeNewChat();
        var existing = chats.find(function (c) { return c.phone === digits; });
        openChat(existing || { id: null, chat_jid: digits + '@s.whatsapp.net', phone: digits, name: null, is_group: false });
    }
    $('wa-new-chat-start').addEventListener('click', startNewChat);
    newChatInputEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); startNewChat(); } });

    // ---------- realtime ----------
    function setBanner(status, reason) {
        var down = status && status !== 'connected';
        bannerEl.classList.toggle('show', !!down);
        if (down) bannerEl.querySelector('span').textContent = reason || 'Perangkat sedang tidak terhubung ke WhatsApp. Pesan baru akan dikirim setelah tersambung lagi.';
    }

    function onMessageNew(d) {
        var msg = norm(d.message || {});
        var mine = d.client_ref && pending.has(d.client_ref);
        var chat = chatIndex.get(d.chat_id);
        var isActive = active && (active.id === d.chat_id || (!active.id && (mine || active.chat_jid === d.chat_jid)));

        if (isActive && !active.id) {
            active.id = d.chat_id;
            loadDetail();
        }
        if (chat) {
            chat.last_message = d.preview || msg.body || chat.last_message;
            chat.last_message_at = msg.sent_at;
            if (!msg.from_me && !(isActive && document.visibilityState === 'visible')) chat.unread_count = (chat.unread_count || 0) + 1;
            sortChats();
            renderedSig = '';
            renderChatList();
        } else {
            reloadChatsSoon();
        }
        if (!isActive) return;

        threadMsgs.push(msg);
        if (mine) {
            var row = pending.get(d.client_ref).row;
            pending.delete(d.client_ref);
            row.replaceWith(bubble(msg));
        } else if (!threadBodyEl.querySelector('[data-id="' + msg.id + '"]')) {
            appendMessage(msg);
        }
        if (!msg.from_me) markRead();
    }

    function onMessageStatus(d) {
        if (!active) return;
        (d.wa_ids || []).forEach(function (waId) {
            var item = threadMsgs.find(function (m) { return m.wa_id === waId; });
            if (!item) return;
            if (d.status) item.status = d.status;
            if (d.edited !== undefined) item.body = d.edited;
            if (d.revoked) item.type = 'revoked';
            var row = threadBodyEl.querySelector('[data-wa-id="' + waId + '"]');
            if (row) row.replaceWith(bubble(item));
        });
    }

    function onOutboxStatus(d) {
        var ref = pendingByOutbox.get(d.outbox_id);
        if (!ref) return;
        if (d.status === 'failed' || d.status === 'skipped') {
            pendingByOutbox.delete(d.outbox_id);
            failPending(ref, d.error);
        } else if (d.status === 'sent') {
            pendingByOutbox.delete(d.outbox_id);
        }
    }

    BizWa.realtime(U.realtime, {
        'message.new': onMessageNew,
        'message.status': onMessageStatus,
        'outbox.status': onOutboxStatus,
        'message.media': function (d) {
            var row = threadBodyEl.querySelector('[data-id="' + d.message_id + '"]');
            var item = threadMsgs.find(function (m) { return m.id === d.message_id; });
            if (row && item) row.replaceWith(bubble(Object.assign(item, { media_url: item.media_url + '?t=' + Date.now() })));
            if (!d.ready) toast('File ini sudah tidak tersedia lagi di WhatsApp.', 'warning');
        },
        'presence': function (d) { if (active && !active.is_group && active.chat_jid === d.chat_jid) renderPresence(d); },
        'poll.vote': function (d) { if (pollShown === d.message_id) showPollResults(d.message_id); },
        'device.status': function (d) { setBanner(d.status, d.reason); },
        // Riwayat chat lama selesai diimpor go_bizbos (datang bertahap): muat ulang daftar chat sekali saja.
        'history.sync': function () {
            clearTimeout(historyTimer);
            historyTimer = setTimeout(loadChats, 1500);
        },
    }, function (live) {
        clearInterval(fallbackTimer);
        if (live) {
            // Setelah sempat terputus: ambil ulang supaya tidak ada pesan yang terlewat.
            if (wasLive) {
                loadChats();
                if (active && active.id) loadMessages();
            }
            wasLive = true;
        } else {
            fallbackTimer = setInterval(loadChats, 20000);
        }
    });

    setBanner(root.dataset.status);
    loadChats();
})();
