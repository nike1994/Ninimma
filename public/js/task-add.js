// Szybkie dodawanie zadań: jedna linijka albo wklejona lista; podgląd tego, co program rozpoznał.
(function () {
    const box = document.querySelector('[data-task-add]');
    if (!box) return;
    const form = box.querySelector('form');
    const area = box.querySelector('[data-lines]');
    const client = box.querySelector('[data-default-client]');
    const preview = box.querySelector('[data-preview]');
    const btn = box.querySelector('[data-add-btn]');
    const label = btn.textContent;
    const token = form.querySelector('input[name=_token]').value;
    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    let timer = null, seq = 0;

    const grow = () => { area.style.height = 'auto'; area.style.height = Math.min(area.scrollHeight, 320) + 'px'; };
    const render = items => {
        preview.hidden = !items.length;
        btn.textContent = items.length > 1 ? label + ' (' + items.length + ')' : label;
        preview.innerHTML = items.map(i => '<li class="' + (i.errors.length ? 'bad' : '') + '"><strong>' + esc(i.title || '—') + '</strong>'
            + (i.client ? '<span class="tp tp-client">' + esc(i.client) + '</span>' : '')
            + (i.person ? '<span class="tp">@' + esc(i.person.split(' ')[0]) + '</span>' : '')
            + (i.due ? '<span class="tp tp-due">' + esc(i.due) + '</span>' : '')
            + (i.estimate ? '<span class="tp">⏱ ' + esc(i.estimate) + '</span>' : '')
            + (i.priority ? '<span class="tp tp-prio">' + esc(i.priority) + '</span>' : '')
            + (i.errors.length ? '<em>' + esc(i.errors.join(' · ')) + '</em>' : '') + '</li>').join('');
    };
    const refresh = () => {
        clearTimeout(timer);
        if (!area.value.trim()) { render([]); return; }
        timer = setTimeout(async () => {
            const my = ++seq;
            const body = new FormData(); body.append('_token', token); body.append('lines', area.value); body.append('client_id', client.value);
            try {
                const r = await fetch(box.dataset.parseUrl, { method: 'POST', body, headers: { Accept: 'application/json' } });
                if (r.ok && my === seq) render(await r.json());
            } catch (e) { /* podgląd jest tylko pomocą */ }
        }, 250);
    };
    area.addEventListener('input', () => { grow(); refresh(); });
    client.addEventListener('change', refresh);
    area.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); if (area.value.trim()) form.requestSubmit(); }
    });
    grow(); refresh();
    if (location.hash === '#add') area.focus();

    // Zestawy: kampanie tylko wybranego klienta.
    document.querySelectorAll('.task-set-apply').forEach(f => {
        const c = f.querySelector('[data-set-client]'), cp = f.querySelector('[data-set-campaign]');
        const sync = () => { [...cp.options].forEach(o => { if (o.value) o.hidden = o.dataset.client !== c.value; }); if (cp.selectedOptions[0]?.hidden) cp.value = ''; };
        c.addEventListener('change', sync); sync();
    });
})();
