/** Widok zlecenia: odpytywanie o nowe wiadomości, wysyłanie bez przeładowania, potwierdzenia przeczytania. */
(function () {
    const thread = document.querySelector('[data-thread]');
    if (!thread || !window.Live) return;

    const live = Live.connect({
        url: thread.dataset.poll,
        activeMs: Number(thread.dataset.activeMs),
        idleMs: Number(thread.dataset.idleMs),
    });
    live.setCursor({ after: thread.dataset.lastId });

    const nearBottom = () => window.innerHeight + window.scrollY >= document.body.scrollHeight - 200;

    const renderSeen = (seen) => {
        thread.querySelectorAll('[data-seen-for]').forEach(el => {
            const id = Number(el.dataset.seenFor);
            const who = (seen || []).filter(s => s.last_seen_id >= id).map(s => s.name);
            el.textContent = who.length ? '✓ ' + thread.dataset.seenLabel + ': ' + who.join(', ') : '';
        });
    };

    live.onMessage(data => {
        if (data.html) {
            const stick = nearBottom();
            thread.querySelector('.empty')?.remove();
            const tmp = document.createElement('div');
            tmp.innerHTML = data.html;
            [...tmp.children].forEach(el => { if (!document.getElementById(el.id)) thread.appendChild(el); });
            if (stick) window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        }
        live.setCursor({ after: data.last_id });
        renderSeen(data.seen);
        const badge = document.querySelector('.ticket-head .badge[class*=status-]');
        if (badge && data.status && !badge.classList.contains('status-' + data.status)) {
            badge.className = 'badge status-' + data.status; badge.textContent = data.status_label;
        }
    });
    live.start();
    live.poll();

    // Wysyłanie wiadomości przez fetch, żeby nie przeładowywać rozmowy.
    const form = document.querySelector('form[data-ajax]');
    form?.addEventListener('submit', async e => {
        e.preventDefault();
        if (Number(form.dataset.pending || 0) > 0) return;
        const fd = new FormData(form);
        if (!String(fd.get('body') || '').trim() && !fd.getAll('uploads[]').length) return;
        const btn = form.querySelector('button.btn-primary');
        btn.disabled = true;
        try {
            const res = await fetch(form.action, { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || res.status);
            form.querySelector('textarea').value = '';
            form.querySelector('.uploader-list').innerHTML = '';
            const internal = form.querySelector('[data-internal]'); if (internal) internal.checked = false;
            live.nudge();
        } catch (err) { alert(err.message); }
        btn.disabled = false;
    });
    form?.querySelector('textarea')?.addEventListener('keydown', e => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) form.requestSubmit();
    });
    form?.querySelector('[data-internal]')?.addEventListener('change', e => form.classList.toggle('internal', e.target.checked));

    // Pole powodu blokady tylko przy statusie "zablokowane".
    const sf = document.querySelector('[data-status-form]');
    if (sf) {
        const sync = () => sf.querySelector('[data-reason]').hidden = sf.status.value !== 'blocked';
        sf.status.addEventListener('change', sync); sync();
    }
    if (location.hash) document.querySelector(location.hash)?.scrollIntoView();
    else window.scrollTo(0, document.body.scrollHeight);
})();
