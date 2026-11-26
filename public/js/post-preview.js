/**
 * Podgląd posta na żywo (Instagram, Facebook, TikTok, LinkedIn…): kadr, ucięcie opisu na „…więcej”, hashtagi.
 * Liczniki znaków i hashtagów z limitami platform wybranych w publikacjach.
 */
(function () {
    const form = document.querySelector('form.content-form[data-preview-accounts]');
    if (!form) return;
    const accounts = JSON.parse(form.dataset.previewAccounts || '{}');
    const limits = JSON.parse(form.dataset.limits || '{}');
    const media = JSON.parse(form.dataset.files || '[]');
    const desc = form.querySelector('[data-desc]'), tags = form.querySelector('[data-hashtags]');
    const stage = form.querySelector('[data-preview-stage]'), tabsEl = form.querySelector('[data-preview-tabs]'), note = form.querySelector('[data-preview-note]');
    const PL = document.documentElement.lang !== 'en';
    const T = PL ? {
        more: 'więcej', fbMore: 'Wyświetl więcej', now: 'Teraz', likes: 'Polubienia', chars: 'znaków', tags: 'hashtagów',
        cutIg: n => `Instagram pokazuje ok. ${n} pierwszych znaków — reszta po „…więcej”.`,
        cutFb: 'Długi tekst Facebook zwija po kilku linijkach („Wyświetl więcej”).',
        crop: (p, r) => `${p} przytnie zdjęcie do proporcji ${r}.`, reel: 'Film pokaże się jako rolka 9:16.',
        noMedia: 'Instagram i TikTok wymagają zdjęcia albo filmu.', strictest: 'najostrzejszy limit',
        follow: 'Obserwuj', fbLike: 'Lubię to', fbComment: 'Komentarz', fbShare: 'Udostępnij',
    } : {
        more: 'more', fbMore: 'See more', now: 'Just now', likes: 'Likes', chars: 'characters', tags: 'hashtags',
        cutIg: n => `Instagram shows about the first ${n} characters — the rest after “…more”.`,
        cutFb: 'Facebook folds long text after a few lines (“See more”).',
        crop: (p, r) => `${p} will crop the image to ${r}.`, reel: 'The video will show as a 9:16 reel.',
        noMedia: 'Instagram and TikTok require a photo or video.', strictest: 'strictest limit', follow: 'Follow', fbLike: 'Like', fbComment: 'Comment', fbShare: 'Share',
    };
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const count = text => ({ chars: [...text].length, tags: (text.match(/(^|[^\p{L}\p{N}_])#[\p{L}\p{N}_]+/gu) || []).length });
    const textFor = caption => (caption && caption.trim()) ? caption : [desc.value.trim(), (tags?.value || '').trim()].filter(Boolean).join('\n\n');
    // Hashtagi, @wzmianki i linki na niebiesko; nowe linie zachowane.
    const rich = t => esc(t).replace(/(^|[\s(])([#@][\p{L}\p{N}_.]+)/gu, '$1<span class="pv-tag">$2</span>').replace(/\n/g, '<br>');

    // Nowo wgrane pliki (z przeglądarki) — od razu w podglądzie.
    form.addEventListener('upload-done', e => {
        const f = e.detail.file;
        if (/^image\//.test(f.type)) media.push({ url: URL.createObjectURL(f), video: false });
        else if (/^video\//.test(f.type) || /\.(mov|mp4|m4v|webm)$/i.test(f.name)) media.push({ url: null, video: true, blob: URL.createObjectURL(f) });
        render();
    });

    const ratioCache = {};
    const ratioOf = url => new Promise(res => {
        if (!url) return res(1);
        if (ratioCache[url]) return res(ratioCache[url]);
        const i = new Image(); i.onload = () => res(ratioCache[url] = i.naturalWidth / i.naturalHeight || 1); i.onerror = () => res(1); i.src = url;
    });

    // Wybrane w publikacjach konta (bez oznaczonych do usunięcia).
    const selected = () => [...form.querySelectorAll('[data-pub-row]')].map(row => {
        const acc = accounts[row.querySelector('[data-account-select]')?.value];
        if (!acc || row.querySelector('input[name$="[remove]"]')?.checked) return null;
        return { ...acc, row, caption: row.querySelector('[data-pub-caption]')?.value || '' };
    }).filter(Boolean);

    let active = null;
    const svg = p => `<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
    const I = {
        heart: svg('<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/>'),
        comment: svg('<path d="M21 12a8 8 0 0 1-11.8 7L4 20l1-4.6A8 8 0 1 1 21 12Z"/>'),
        send: svg('<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>'),
        save: svg('<path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>'),
        like: svg('<path d="M7 10v12"/><path d="M15 5.9 14 10h5.8a2 2 0 0 1 2 2.3l-1.4 8A2 2 0 0 1 18.4 22H7V10l4-8a3 3 0 0 1 4 3.9Z"/>'),
        share: svg('<path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="m16 6-4-4-4 4"/><path d="M12 2v13"/>'),
        play: '<svg viewBox="0 0 24 24" width="34" height="34"><circle cx="12" cy="12" r="12" fill="rgba(0,0,0,.45)"/><path d="M10 8v8l6-4z" fill="#fff"/></svg>',
    };
    const avatar = name => `<span class="pv-avatar">${esc((name || '?').trim().slice(0, 1).toUpperCase())}</span>`;

    const mediaBox = async (platform, notes) => {
        const first = media[0];
        if (!first) return `<div class="pv-media pv-empty" style="aspect-ratio:1/1"><span>🖼</span></div>`;
        if (first.video) {
            notes.push(T.reel);
            return `<div class="pv-media pv-video" style="aspect-ratio:9/16">${first.url ? `<img src="${esc(first.url)}" alt="">` : ''}<span class="pv-play">${I.play}</span></div>`;
        }
        const r = await ratioOf(first.url);
        // Instagram: od 4:5 (pion) do 1.91:1 (poziom); Facebook podobnie w kanale.
        const min = 0.8, max = 1.91;
        const shown = Math.min(max, Math.max(min, r));
        if (Math.abs(shown - r) > 0.01) notes.push(T.crop(platform, r < min ? '4:5' : '1.91:1'));
        const dots = media.filter(m => !m.video).length > 1 ? `<div class="pv-dots">${media.filter(m => !m.video).slice(0, 10).map((_, i) => `<i${i ? '' : ' class="on"'}></i>`).join('')}</div>` : '';
        return `<div class="pv-media" style="aspect-ratio:${shown.toFixed(3)}"><img src="${esc(first.url)}" alt="">${dots}</div>`;
    };

    const cut = (text, n) => {
        const chars = [...text];
        // Instagram ucina też po 2 linijkach.
        const lines = text.split('\n');
        let short = chars.length > n ? chars.slice(0, n).join('') : text;
        if (lines.length > 2 && short.split('\n').length > 2) short = lines.slice(0, 2).join('\n');
        // Ucięcie na granicy słowa, bez kropki/przecinka przed „…”.
        return short.length < text.length ? short.replace(/\s+\S*$/, '').replace(/[\s.,;:!?…-]+$/u, '') : null;
    };

    const views = {
        async instagram(acc, text, notes) {
            const n = limits.instagram.more, short = cut(text, n);
            if (short) notes.push(T.cutIg(n));
            if (!media.length) notes.push(T.noMedia);
            return `<div class="pv pv-ig">
                <div class="pv-head">${avatar(acc.handle)}<strong>${esc(acc.handle)}</strong><span class="pv-dots-menu">•••</span></div>
                ${await mediaBox('Instagram', notes)}
                <div class="pv-actions">${I.heart}${I.comment}${I.send}<span class="pv-spacer"></span>${I.save}</div>
                <div class="pv-caption" data-full="${esc(text)}"><strong>${esc(acc.handle)}</strong> ${short ? rich(short) + `… <button type="button" class="pv-more">${T.more}</button>` : rich(text)}</div>
                <div class="pv-time">${T.now}</div></div>`;
        },
        async facebook(acc, text, notes) {
            const short = cut(text, limits.facebook.more);
            if (short) notes.push(T.cutFb);
            return `<div class="pv pv-fb">
                <div class="pv-head">${avatar(acc.name)}<span><strong>${esc(acc.name)}</strong><small>${T.now} · 🌐</small></span></div>
                <div class="pv-caption pv-fb-text" data-full="${esc(text)}">${short ? rich(short) + `… <button type="button" class="pv-more">${T.fbMore}</button>` : rich(text)}</div>
                ${media.length ? await mediaBox('Facebook', notes) : ''}
                <div class="pv-fb-actions"><span>${I.like} ${T.fbLike}</span><span>${I.comment} ${T.fbComment}</span><span>${I.share} ${T.fbShare}</span></div></div>`;
        },
        async tiktok(acc, text, notes) {
            const short = cut(text, limits.tiktok.more);
            if (!media.some(m => m.video)) notes.push(T.noMedia);
            const bg = media[0]?.url ? `<img src="${esc(media[0].url)}" alt="">` : '';
            return `<div class="pv pv-tt"><div class="pv-tt-frame">${bg}
                <div class="pv-tt-side"><span>${avatar(acc.handle)}</span><span>${I.heart}</span><span>${I.comment}</span><span>${I.share}</span></div>
                <div class="pv-tt-text"><strong>@${esc(acc.handle)}</strong><div class="pv-caption" data-full="${esc(text)}">${short ? rich(short) + `… <button type="button" class="pv-more">${T.more}</button>` : rich(text)}</div></div></div></div>`;
        },
        async linkedin(acc, text, notes) {
            const short = cut(text, limits.linkedin.more);
            return `<div class="pv pv-fb pv-li">
                <div class="pv-head">${avatar(acc.name)}<span><strong>${esc(acc.name)}</strong><small>${T.now}</small></span></div>
                <div class="pv-caption pv-fb-text" data-full="${esc(text)}">${short ? rich(short) + `… <button type="button" class="pv-more">${T.more}</button>` : rich(text)}</div>
                ${media.length ? await mediaBox('LinkedIn', notes) : ''}</div>`;
        },
    };
    views.youtube = views.linkedin; views.pinterest = views.instagram;

    // ---------------------------------------------------------------- liczniki
    const counterHtml = (text, platforms) => {
        const c = count(text);
        const lims = platforms.map(p => ({ p, ...limits[p.platform] })).filter(l => l.chars);
        if (!lims.length) return `<span>${c.chars} ${T.chars} · ${c.tags} ${T.tags}</span>`;
        const charL = lims.reduce((a, b) => (b.chars < a.chars ? b : a));
        const tagLs = lims.filter(l => l.tags);
        const tagL = tagLs.length ? tagLs.reduce((a, b) => (b.tags < a.tags ? b : a)) : null;
        const pct = c.chars / charL.chars;
        return `<span class="${c.chars > charL.chars ? 'over' : pct > .9 ? 'near' : ''}">${c.chars} / ${charL.chars} ${T.chars} <small>(${esc(charL.p.label)})</small></span>`
            + (tagL ? ` · <span class="${c.tags > tagL.tags ? 'over' : ''}">${c.tags} / ${tagL.tags} ${T.tags} <small>(${esc(tagL.p.label)})</small></span>` : ` · <span>${c.tags} ${T.tags}</span>`)
            + `<i class="text-bar"><i style="width:${Math.min(100, pct * 100).toFixed(1)}%" class="${c.chars > charL.chars ? 'over' : pct > .9 ? 'near' : ''}"></i></i>`;
    };

    const counters = sel => {
        // Główny licznik: tekst wspólny dla kont bez własnego opisu.
        const shared = sel.filter(s => !s.caption.trim());
        form.querySelector('[data-counter="main"]').innerHTML = counterHtml(textFor(''), shared.length ? shared : sel);
        form.querySelectorAll('[data-pub-row]').forEach(row => {
            const box = row.querySelector('[data-counter="pub"]'), cap = row.querySelector('[data-pub-caption]');
            if (!box || !cap) return;
            const acc = accounts[row.querySelector('[data-account-select]')?.value];
            box.innerHTML = cap.value.trim() && acc ? counterHtml(cap.value, [acc]) : '';
        });
    };

    // ---------------------------------------------------------------- render
    let seq = 0;
    const render = async () => {
        const my = ++seq;
        const sel = selected();
        counters(sel);
        const list = sel.length ? sel : [{ platform: 'instagram', label: 'Instagram', handle: 'profil', name: 'Profil', caption: '' }];
        // Jedna karta na konto (Instagram i Facebook tego samego klienta obok siebie w zakładkach).
        const keys = list.map((s, i) => s.platform + ':' + i);
        if (!keys.includes(active)) active = keys[0];
        tabsEl.innerHTML = list.map((s, i) => `<button type="button" role="tab" class="chip chip-sm${keys[i] === active ? ' chip-dark' : ''}" data-key="${keys[i]}" aria-selected="${keys[i] === active}">${esc(s.label)}${list.filter(x => x.platform === s.platform).length > 1 ? ' · ' + esc(s.handle) : ''}</button>`).join('');
        const cur = list[keys.indexOf(active)];
        const notes = [];
        const html = await (views[cur.platform] || views.linkedin)(cur, textFor(cur.caption), notes);
        if (my !== seq) return;
        stage.innerHTML = html;
        note.textContent = notes.join(' ');
    };

    tabsEl.addEventListener('click', e => { const b = e.target.closest('[data-key]'); if (b) { active = b.dataset.key; render(); } });
    stage.addEventListener('click', e => {
        const b = e.target.closest('.pv-more'); if (!b) return;
        const cap = b.closest('.pv-caption'); const handle = cap.querySelector(':scope > strong');
        cap.innerHTML = (handle ? handle.outerHTML + ' ' : '') + rich(cap.dataset.full);
    });
    let t;
    const later = () => { clearTimeout(t); t = setTimeout(render, 150); };
    form.addEventListener('input', later);
    form.addEventListener('change', later);
    render();
})();
