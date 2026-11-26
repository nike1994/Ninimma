/**
 * Kalendarze publikacji: szybki podgląd po najechaniu, przeciąganie na inny dzień / godzinę,
 * dodawanie posta w wybranym dniu (miesiąc: „+”, tydzień: dwuklik w godzinę).
 */
(function () {
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
    const L = document.documentElement.lang === 'en'
        ? { auto: 'publishes automatically', client: 'Client', moving: 'Moving…', video: 'video' }
        : { auto: 'publikuje się automatycznie', client: 'Klient', moving: 'Przenoszę…', video: 'film' };

    // ------------------------------------------------------------ toast
    let toastEl, toastTimer;
    const toast = (text, error) => {
        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.className = 'toast cal-toast';
            toastEl.setAttribute('role', 'status');
            document.body.appendChild(toastEl);
        }
        toastEl.textContent = text;
        toastEl.classList.toggle('is-error', !!error);
        requestAnimationFrame(() => toastEl.classList.add('show'));
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toastEl.classList.remove('show'), error ? 6000 : 3000);
    };

    // ------------------------------------------------------------ podgląd
    const peek = document.createElement('div');
    peek.className = 'pub-peek';
    peek.hidden = true;
    document.body.appendChild(peek);
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    let dragging = false;

    const showPeek = el => {
        if (dragging) return;
        let d; try { d = JSON.parse(el.dataset.peek); } catch (e) { return; }
        peek.innerHTML =
            (d.img ? `<div class="pub-peek-img"><img src="${esc(d.img)}" alt="">${d.video ? `<span>▶ ${L.video}</span>` : ''}</div>` : '')
            + `<div class="pub-peek-body"><small class="muted">${esc(d.account)}</small><strong>${esc(d.title)}</strong>`
            + `<div class="pub-peek-meta"><span class="pill pub-peek-${esc(d.state)}">${esc(d.status)}</span> ${esc(d.when)}</div>`
            + (d.text ? `<p>${esc(d.text)}</p>` : '') + (d.tags ? `<p class="pub-peek-tags">${esc(d.tags)}</p>` : '')
            + (d.client ? `<p class="small">${L.client}: <strong>${esc(d.client)}</strong></p>` : '')
            + (d.auto ? `<p class="small pub-peek-auto">⚡ ${L.auto}</p>` : '')
            + (el.dataset.lic ? `<p class="small pub-peek-lic">${esc(el.dataset.lic)}</p>` : '') + '</div>';
        peek.hidden = false;
        const r = el.getBoundingClientRect(), w = peek.offsetWidth, h = peek.offsetHeight;
        let left = r.right + 10, top = r.top - 10;
        if (left + w > innerWidth - 8) left = Math.max(8, r.left - w - 10);
        if (top + h > innerHeight - 8) top = Math.max(8, innerHeight - h - 8);
        peek.style.left = left + scrollX + 'px';
        peek.style.top = top + scrollY + 'px';
    };
    const hidePeek = () => { peek.hidden = true; };
    document.querySelectorAll('[data-peek]').forEach(el => {
        el.addEventListener('mouseenter', () => showPeek(el));
        el.addEventListener('mouseleave', hidePeek);
        el.addEventListener('focus', () => showPeek(el));
        el.addEventListener('blur', hidePeek);
    });

    // ------------------------------------------------------------ przenoszenie
    const move = async (el, at) => {
        const res = await fetch(el.dataset.move, {
            method: 'PATCH', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ at }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || data.errors && Object.values(data.errors)[0] || 'HTTP ' + res.status);
        return data;
    };
    const updatePeek = (el, data) => {
        try { const d = JSON.parse(el.dataset.peek); d.when = data.message.replace(/^[^:]*:\s*/, ''); el.dataset.peek = JSON.stringify(d); } catch (e) { /* zostaje */ }
    };

    let current = null;
    document.querySelectorAll('[data-move]').forEach(el => {
        el.addEventListener('dragstart', e => {
            current = el; dragging = true; hidePeek();
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', el.dataset.move);
            setTimeout(() => el.classList.add('is-dragging'), 0);
        });
        el.addEventListener('dragend', () => {
            el.classList.remove('is-dragging'); dragging = false; current = null;
            document.querySelectorAll('.drop-target').forEach(t => t.classList.remove('drop-target'));
        });
    });

    // Miesiąc: upuszczenie na dzień, godzina bez zmian.
    document.querySelectorAll('.month-cell[data-date]').forEach(cell => {
        cell.addEventListener('dragover', e => { if (!current) return; e.preventDefault(); cell.classList.add('drop-target'); });
        cell.addEventListener('dragleave', e => { if (!cell.contains(e.relatedTarget)) cell.classList.remove('drop-target'); });
        cell.addEventListener('drop', async e => {
            e.preventDefault(); cell.classList.remove('drop-target');
            const el = current; if (!el) return;
            const from = el.parentElement;
            if (from === cell) return;
            const before = el.nextSibling;
            cell.appendChild(el); el.classList.add('is-saving');
            try {
                const data = await move(el, cell.dataset.date + ' ' + el.dataset.time);
                updatePeek(el, data); toast(data.message);
            } catch (err) {
                from.insertBefore(el, before); toast(err.message, true);
            } finally { el.classList.remove('is-saving'); }
        });
    });

    // Tydzień: dzień + godzina z miejsca upuszczenia (siatka 8:00–21:00, co 15 min).
    const slotTime = (slots, clientY) => {
        const r = slots.getBoundingClientRect();
        const frac = Math.min(1, Math.max(0, (clientY - r.top) / r.height));
        let mins = Math.round((8 + frac * 13) * 4) * 15;
        mins = Math.min(mins, 23 * 60 + 45);
        return [String(Math.floor(mins / 60)).padStart(2, '0') + ':' + String(mins % 60).padStart(2, '0'), frac];
    };
    document.querySelectorAll('.week-day[data-date] .week-slots').forEach(slots => {
        const day = slots.closest('.week-day');
        slots.addEventListener('dragover', e => { if (!current) return; e.preventDefault(); day.classList.add('drop-target'); });
        slots.addEventListener('dragleave', e => { if (!slots.contains(e.relatedTarget)) day.classList.remove('drop-target'); });
        slots.addEventListener('drop', async e => {
            e.preventDefault(); day.classList.remove('drop-target');
            const el = current; if (!el) return;
            const [time] = slotTime(slots, e.clientY);
            const from = el.parentElement, oldTop = el.style.top;
            const [h, m] = time.split(':').map(Number);
            slots.appendChild(el); el.style.top = Math.max(0, Math.min(100, ((h + m / 60) - 8) / 13 * 100)) + '%'; el.classList.add('is-saving');
            try {
                const data = await move(el, day.dataset.date + ' ' + time);
                el.dataset.time = data.time; updatePeek(el, data); toast(data.message);
            } catch (err) {
                from.appendChild(el); el.style.top = oldTop; toast(err.message, true);
            } finally { el.classList.remove('is-saving'); }
        });
        // Dwuklik w pustym miejscu: nowy post na ten dzień i godzinę.
        slots.addEventListener('dblclick', e => {
            if (e.target.closest('.week-item') || !slots.dataset.create) return;
            const [time] = slotTime(slots, e.clientY);
            location.href = slots.dataset.create + '&time=' + encodeURIComponent(time);
        });
    });
})();
