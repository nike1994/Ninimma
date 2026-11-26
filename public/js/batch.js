/**
 * Planowanie seryjne: przeciągnięte pliki → osobne posty, wysyłka w tle, rozkładanie dat wg dni tygodnia.
 */
(function () {
    const form = document.querySelector('[data-batch]');
    if (!form) return;
    const U = window.NinimmaUpload;
    const rows = form.querySelector('[data-rows]'), tpl = form.querySelector('[data-row-template]');
    const PL = document.documentElement.lang !== 'en';
    const plural = (n, one, few, many) => n === 1 ? one : (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? few : many);
    const T = PL ? {
        posts: n => `${n} ${plural(n, 'post', 'posty', 'postów')}`,
        pubs: n => `${n} ${plural(n, 'publikacja', 'publikacje', 'publikacji')}`,
        submit: n => n ? `Utwórz ${n} ${plural(n, 'post', 'posty', 'postów')}` : 'Utwórz posty',
        noDays: 'Zaznacz co najmniej jeden dzień tygodnia.',
    } : {
        posts: n => `${n} post${n === 1 ? '' : 's'}`, pubs: n => `${n} publication${n === 1 ? '' : 's'}`,
        submit: n => n ? `Create ${n} post${n === 1 ? '' : 's'}` : 'Create posts', noDays: 'Select at least one weekday.',
    };

    let n = Math.max(-1, ...[...rows.querySelectorAll('[data-row] [name$="[date]"]')].map(i => parseInt(i.name.match(/rows\[(\d+)\]/)?.[1] ?? -1, 10))) + 1;
    let pending = 0;
    const setPending = d => { pending += d; form.dataset.pending = pending; refresh(); };

    const refresh = () => {
        const all = [...rows.querySelectorAll('[data-row]')];
        all.forEach((r, i) => { r.querySelector('[data-num]').textContent = i + 1; });
        const accounts = form.querySelectorAll('.batch-acc input[type=checkbox]:checked').length;
        form.querySelector('[data-count]').textContent = all.length;
        form.querySelector('[data-summary]').textContent = all.length
            ? `${T.posts(all.length)} × ${accounts} = ${T.pubs(all.length * accounts)}` : '';
        form.querySelector('[data-submit-label]').textContent = T.submit(all.length);
    };

    const addRow = () => {
        rows.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__I__', n++));
        const row = rows.lastElementChild;
        bindRow(row); refresh();
        return row;
    };

    const attachFile = async (row, raw) => {
        const idx = row.querySelector('[name$="[date]"]').name.match(/rows\[(\d+)\]/)[1];
        const thumb = document.createElement('span');
        thumb.className = 'batch-thumb is-uploading';
        thumb.innerHTML = '<progress max="1" value="0"></progress>';
        row.querySelector('[data-media]').insertBefore(thumb, row.querySelector('.batch-add-file'));
        const isVideo = /^video\//.test(raw.type) || /\.(mov|mp4|m4v|webm)$/i.test(raw.name);
        // Podgląd od razu: zdjęcie albo kadr filmu z przeglądarki.
        let posterBlob = null;
        if (isVideo) {
            posterBlob = await U.videoPoster(raw);
            thumb.insertAdjacentHTML('afterbegin', posterBlob ? `<img src="${URL.createObjectURL(posterBlob)}" alt=""><i class="batch-play">▶</i>` : '<i class="batch-play">▶</i>');
        } else {
            thumb.insertAdjacentHTML('afterbegin', `<img src="${URL.createObjectURL(raw)}" alt="">`);
        }
        const title = row.querySelector('.batch-title');
        if (!title.value && !title.placeholder.includes('·')) title.placeholder = raw.name.replace(/\.[^.]+$/, '') + ' · ' + title.placeholder;
        setPending(1);
        try {
            const file = isVideo ? raw : await U.compressImage(raw);
            const res = await U.uploadFile(form.dataset.start, file, p => { thumb.querySelector('progress').value = p; });
            thumb.insertAdjacentHTML('beforeend', `<input type="hidden" name="rows[${idx}][uploads][]" value="${res.usage_id}">`);
            if (isVideo && posterBlob) {
                const fd = new FormData(); fd.append('poster', posterBlob, 'poster.jpg');
                await U.post(form.dataset.poster.replace('__ID__', res.usage_id), fd).catch(() => {});
            }
            thumb.classList.remove('is-uploading');
            thumb.querySelector('progress').remove();
        } catch (e) {
            thumb.classList.add('is-error'); thumb.title = e.message;
        }
        setPending(-1);
    };

    const bindRow = row => {
        row.querySelector('[data-remove]').addEventListener('click', () => { row.remove(); refresh(); });
        row.querySelector('[data-row-files]').addEventListener('change', e => { [...e.target.files].forEach(f => attachFile(row, f)); e.target.value = ''; });
    };
    rows.querySelectorAll('[data-row]').forEach(bindRow);

    // Pusty wiersz (bez pliku i tekstu) bierze plik jako pierwszy — żeby nie zostawały puste posty.
    const emptyRow = () => [...rows.querySelectorAll('[data-row]')].find(r => !r.querySelector('.batch-thumb:not(.batch-add-file)')
        && !r.querySelector('textarea').value.trim() && !r.querySelector('.batch-title').value.trim());

    // Każdy przeciągnięty plik = osobny post.
    const addFiles = files => [...files].filter(f => /^(image|video)\//.test(f.type) || /\.(mov|mp4|m4v|webm|jpe?g|png|webp|heic)$/i.test(f.name))
        .sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true }))
        .forEach(f => attachFile(emptyRow() || addRow(), f));
    const drop = form.querySelector('[data-drop]');
    form.querySelector('[data-files]').addEventListener('change', e => { addFiles(e.target.files); e.target.value = ''; });
    ['dragover', 'dragenter'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('dragging'); }));
    drop.addEventListener('dragleave', () => drop.classList.remove('dragging'));
    drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('dragging'); addFiles(e.dataTransfer.files); });

    form.querySelector('[data-add-row]').addEventListener('click', () => addRow().querySelector('.batch-title').focus());

    // Rozłożenie dat: kolejne posty na kolejne zaznaczone dni tygodnia od daty startu.
    form.querySelector('[data-fill-dates]').addEventListener('click', () => {
        const days = [...form.querySelectorAll('[data-weekday]:checked')].map(c => Number(c.dataset.weekday));
        if (!days.length) { alert(T.noDays); return; }
        const d = new Date(form.querySelector('[data-start-date]').value + 'T12:00:00');
        if (isNaN(d)) return;
        rows.querySelectorAll('[data-row] [data-date]').forEach(input => {
            while (!days.includes(d.getDay())) d.setDate(d.getDate() + 1);
            input.value = d.toISOString().slice(0, 10);
            d.setDate(d.getDate() + 1);
        });
    });

    form.querySelectorAll('.batch-acc input[type=checkbox]').forEach(c => c.addEventListener('change', refresh));
    form.querySelectorAll('[data-tags]').forEach(b => b.addEventListener('click', () => {
        const input = form.querySelector('[data-hashtags]');
        const have = new Set(input.value.split(/\s+/).filter(Boolean));
        b.dataset.tags.split(/\s+/).filter(Boolean).forEach(t => have.add(t));
        input.value = [...have].join(' ');
    }));
    if (!rows.children.length) addRow();
    refresh();
})();
