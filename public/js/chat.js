// Okno wiadomości: lista rozmów, rozmowa i odpowiedź — na każdej stronie (panel) albo w osobnym oknie.
// Stan (otwarte, rozmowa, filtr) i szkice odpowiedzi trzymamy w localStorage, więc przeżywają przejście na inną stronę.
(function () {
    const root = document.querySelector('[data-chat]');
    if (!root) return;
    const $ = s => root.querySelector(s);
    const full = root.dataset.mode === 'full';
    const t = JSON.parse(root.dataset.i18n);
    const token = document.querySelector('meta[name=csrf-token]').content;
    const panel = $('[data-chat-panel]'), badge = $('[data-chat-badge]');
    const listView = $('[data-chat-list-view]'), conv = $('[data-chat-conv]');
    const threadsEl = $('[data-chat-threads]'), msgs = $('[data-chat-msgs]');
    const body = $('[data-chat-body]'), internal = $('[data-chat-internal]');
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const KEY = 'chat:state';
    let state = Object.assign({ open: false, ticket: null, filter: 'all' }, JSON.parse(localStorage.getItem(KEY) || '{}'));
    const fromUrl = new URLSearchParams(location.search).get('t');
    if (full && fromUrl) state.ticket = Number(fromUrl);
    let threads = [], current = null, lastId = 0, pollTimer = null, listTimer = null, q = '';
    const save = () => localStorage.setItem(KEY, JSON.stringify(state));

    const setBadge = n => { if (badge) { badge.textContent = n; badge.hidden = !n; } };
    const getJson = async url => { const r = await fetch(url, { headers: { Accept: 'application/json' } }); if (!r.ok) throw new Error(r.status); return r.json(); };

    // ---------------------------------------------------------------- lista rozmów
    async function loadThreads() {
        const u = new URL(root.dataset.threadsUrl, location.origin);
        u.searchParams.set('filter', state.filter); if (q) u.searchParams.set('q', q);
        try {
            const d = await getJson(u);
            threads = d.threads; setBadge(d.unread_total); renderThreads();
            if (state.ticket && !current) { const th = threads.find(x => x.id === state.ticket); if (th) openConv(th); else if (full || state.open) openById(state.ticket); }
        } catch (e) { /* cicho — spróbujemy przy kolejnym odświeżeniu */ }
    }
    async function openById(id) { // rozmowa spoza bieżącej listy (np. po filtrze)
        const u = new URL(root.dataset.threadsUrl, location.origin); u.searchParams.set('q', String(id)); u.searchParams.set('filter', 'done');
        try { const d = await getJson(u); const th = d.threads.find(x => x.id === id); if (th) openConv(th); } catch (e) {}
    }
    function renderThreads() {
        if (!threads.length) { threadsEl.innerHTML = '<p class="muted small chat-pad">' + esc(t.empty) + '</p>'; return; }
        threadsEl.innerHTML = threads.map(th => '<button type="button" class="chat-thread' + (th.unread ? ' unread' : '') + '" data-id="' + th.id + '">'
            + '<span class="ct-top"><strong>' + esc(th.title) + '</strong><time>' + esc(th.at || '') + '</time></span>'
            + '<span class="ct-sub">' + (th.client ? '<b>' + esc(th.client) + '</b> · ' : '') + '<span class="pill pill-' + th.status + '"><i></i>' + esc(th.status_label) + '</span></span>'
            + (th.last ? '<span class="ct-last' + (th.last.internal ? ' internal' : '') + '">' + esc(th.last.author) + ': ' + esc(th.last.text) + '</span>' : '')
            + '</button>').join('');
    }
    threadsEl.addEventListener('click', e => { const b = e.target.closest('.chat-thread'); if (b) openConv(threads.find(x => x.id === Number(b.dataset.id))); });
    $('[data-chat-filters]').addEventListener('click', e => {
        const b = e.target.closest('[data-f]'); if (!b) return;
        state.filter = b.dataset.f; save(); markFilter(); loadThreads();
    });
    const markFilter = () => root.querySelectorAll('[data-f]').forEach(b => b.classList.toggle('on', b.dataset.f === state.filter));
    let qTimer; $('[data-chat-q]').addEventListener('input', e => { clearTimeout(qTimer); qTimer = setTimeout(() => { q = e.target.value.trim(); loadThreads(); }, 300); });

    // ---------------------------------------------------------------- rozmowa
    function openConv(th) {
        if (!th) return;
        current = th; lastId = 0; state.ticket = th.id; save();
        listView.hidden = true; conv.hidden = false;
        $('[data-chat-back]').hidden = false;
        $('[data-chat-title]').textContent = th.title;
        $('[data-chat-sub]').textContent = (th.client ? th.client + ' · ' : '') + '#' + th.id;
        const open = $('[data-chat-open-ticket]'); open.href = th.url; open.hidden = false;
        msgs.innerHTML = '';
        body.value = localStorage.getItem('chat:draft:' + th.id) || '';
        $('[data-chat-reply]').hidden = !th.can_reply;
        poll(true);
        clearInterval(pollTimer); pollTimer = setInterval(() => { if (!document.hidden) poll(false); }, 4000);
        if (th.can_reply && (full || state.open)) setTimeout(() => body.focus(), 50);
    }
    function backToList() {
        current = null; state.ticket = null; save(); clearInterval(pollTimer);
        conv.hidden = true; listView.hidden = false;
        $('[data-chat-back]').hidden = true; $('[data-chat-open-ticket]').hidden = true;
        $('[data-chat-title]').textContent = t.title; $('[data-chat-sub]').textContent = '';
        loadThreads();
    }
    $('[data-chat-back]').addEventListener('click', backToList);

    async function poll(first) {
        if (!current) return;
        const th = current;
        try {
            const d = await getJson(th.poll + '?after=' + lastId);
            if (th !== current) return;
            const nearBottom = msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight < 80;
            if (d.html) { msgs.insertAdjacentHTML('beforeend', d.html); addCopyButtons(); }
            else if (first) msgs.innerHTML = '<p class="muted small chat-pad">' + esc(th.can_reply ? t.no_messages : t.readonly) + '</p>';
            if (first && d.html && !th.can_reply) msgs.insertAdjacentHTML('beforeend', '<p class="muted small chat-pad">' + esc(t.readonly) + '</p>');
            lastId = d.last_id;
            if (first || (d.html && nearBottom)) msgs.scrollTop = msgs.scrollHeight;
            if (d.html && th.unread) { th.unread = false; refreshCount(); }
        } catch (e) {}
    }
    function addCopyButtons() {
        msgs.querySelectorAll('.msg-body:not([data-copy-ready])').forEach(el => {
            el.dataset.copyReady = '1';
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'chat-copy'; b.textContent = t.copy;
            b.addEventListener('click', async () => {
                try { await navigator.clipboard.writeText(el.innerText.trim()); } catch (e) {
                    const r = document.createRange(); r.selectNodeContents(el); const s = getSelection(); s.removeAllRanges(); s.addRange(r); document.execCommand('copy');
                }
                b.textContent = t.copied; setTimeout(() => { b.textContent = t.copy; }, 1500);
            });
            el.after(b);
        });
    }

    // ---------------------------------------------------------------- odpowiedź
    body.addEventListener('input', () => { if (current) localStorage.setItem('chat:draft:' + current.id, body.value); });
    body.addEventListener('keydown', e => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); $('[data-chat-reply]').requestSubmit(); } });
    $('[data-chat-reply]').addEventListener('submit', async e => {
        e.preventDefault();
        if (!current || !body.value.trim()) return;
        const btn = e.target.querySelector('button'); btn.disabled = true;
        const f = new FormData(); f.append('_token', token); f.append('body', body.value);
        if (internal?.checked) f.append('internal', '1');
        try {
            const r = await fetch(current.send, { method: 'POST', body: f, headers: { Accept: 'application/json' } });
            if (!r.ok) throw new Error(r.status);
            body.value = ''; localStorage.removeItem('chat:draft:' + current.id); if (internal) internal.checked = false;
            if (msgs.querySelector('.chat-pad')) msgs.innerHTML = '';
            await poll(false); msgs.scrollTop = msgs.scrollHeight;
        } catch (err) { alert(t.error); }
        btn.disabled = false; body.focus();
    });

    // ---------------------------------------------------------------- otwieranie / zamykanie
    async function refreshCount() {
        try { const d = await getJson(root.dataset.threadsUrl + '?count=1'); setBadge(d.unread_total); } catch (e) {}
    }
    function setOpen(open) {
        state.open = open; save();
        panel.hidden = !open; root.classList.toggle('open', open);
        clearInterval(listTimer);
        if (open) {
            if (!current) loadThreads();
            listTimer = setInterval(() => { if (!document.hidden && !current) loadThreads(); }, 20000);
        } else {
            clearInterval(pollTimer); current = null; listView.hidden = false; conv.hidden = true;
            $('[data-chat-back]').hidden = true; $('[data-chat-open-ticket]').hidden = true;
            $('[data-chat-title]').textContent = t.title; $('[data-chat-sub]').textContent = '';
        }
    }
    if (full) {
        markFilter(); loadThreads();
        setInterval(() => { if (!document.hidden && !current) loadThreads(); }, 20000);
        return;
    }
    $('[data-chat-toggle]').addEventListener('click', () => setOpen(panel.hidden));
    $('[data-chat-close]').addEventListener('click', () => setOpen(false));
    $('[data-chat-popout]').addEventListener('click', () => {
        const u = new URL(root.dataset.windowUrl, location.origin); if (current) u.searchParams.set('t', current.id);
        window.open(u, 'ninimma-chat', 'width=440,height=780');
        setOpen(false);
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden && !e.target.closest('input, textarea, select')) setOpen(false); });
    markFilter();
    if (state.open) setOpen(true);
    setInterval(() => { if (!document.hidden && panel.hidden) refreshCount(); }, 30000);
})();
