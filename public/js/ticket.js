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

    const nearBottom = () => thread.scrollHeight - thread.scrollTop - thread.clientHeight < 150;

    const renderSeen = (seen) => {
        thread.querySelectorAll('[data-seen-for]').forEach(el => {
            const id = Number(el.dataset.seenFor);
            const who = (seen || []).filter(s => s.last_seen_id >= id).map(s => s.name);
            el.textContent = who.length ? '✓✓' : '✓';
            el.title = who.length ? thread.dataset.seenLabel + ': ' + who.join(', ') : '';
            el.classList.toggle('read', who.length > 0);
        });
    };

    live.onMessage(data => {
        if (data.html) {
            const stick = nearBottom();
            thread.querySelector('.empty')?.remove();
            const tmp = document.createElement('div');
            tmp.innerHTML = data.html;
            [...tmp.children].forEach(el => { if (!document.getElementById(el.id)) thread.appendChild(el); });
            if (stick) thread.scrollTo({ top: thread.scrollHeight, behavior: 'smooth' });
        }
        live.setCursor({ after: data.last_id });
        renderSeen(data.seen);
        const label = document.querySelector('[data-status-label]');
        if (label && data.status_label && label.textContent.trim() !== data.status_label) label.textContent = data.status_label;
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
        const btn = form.querySelector('.btn-send');
        btn.disabled = true;
        try {
            const res = await fetch(form.action, { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || res.status);
            form.querySelector('textarea').value = '';
            form.querySelector('.uploader-list').innerHTML = '';
            setInternal(false);
            live.nudge();
        } catch (err) { alert(err.message); }
        btn.disabled = false;
    });
    form?.querySelector('textarea')?.addEventListener('keydown', e => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) form.requestSubmit();
    });
    // Przełącznik: wiadomość do klienta / notatka wewnętrzna.
    function setInternal(on) {
        const input = form?.querySelector('[data-internal]');
        if (!input) return;
        input.value = on ? '1' : '0';
        form.classList.toggle('internal', on);
        form.querySelectorAll('[data-segmented] button').forEach(b => b.classList.toggle('active', b.dataset.value === (on ? '1' : '0')));
    }
    form?.querySelectorAll('[data-segmented] button').forEach(b => b.addEventListener('click', () => setInternal(b.dataset.value === '1')));

    // Zakładki widoku zlecenia.
    document.querySelectorAll('[data-tabs] [data-tab]').forEach(tab => tab.addEventListener('click', () => {
        document.querySelectorAll('[data-tabs] [data-tab]').forEach(t => t.classList.toggle('active', t === tab));
        document.querySelectorAll('[data-panel]').forEach(p => p.hidden = p.dataset.panel !== tab.dataset.tab);
    }));

    // Pole powodu blokady tylko przy statusie "zablokowane".
    const sf = document.querySelector('[data-status-form]');
    if (sf) {
        const sync = () => sf.querySelector('[data-reason]').hidden = sf.status.value !== 'blocked';
        sf.status.addEventListener('change', sync); sync();
    }
    if (location.hash) document.querySelector(location.hash)?.scrollIntoView({ block: 'center' });
    else thread.scrollTop = thread.scrollHeight;
})();
