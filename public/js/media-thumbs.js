// Miniatury filmów: kadr wczytuje się dopiero, gdy miniatura jest widoczna; po najechaniu film gra bez dźwięku.
// Tryb chroniony (klient): znak wodny na podglądzie, odtwarzacz bez pobierania i bez menu kontekstowego.
(function () {
    const load = v => { if (!v.src && v.dataset.src) { v.preload = 'metadata'; v.src = v.dataset.src; } };
    const io = 'IntersectionObserver' in window ? new IntersectionObserver(es => es.forEach(e => {
        if (e.isIntersecting) { load(e.target); io.unobserve(e.target); }
    }), { rootMargin: '200px' }) : null;

    // Kafelek znaku wodnego (SVG) z nazwą klienta — ukośnie, powtarzany.
    const tile = text => {
        const t = String(text).replace(/[<&>"]/g, c => ({ '<': '&lt;', '&': '&amp;', '>': '&gt;', '"': '&quot;' }[c]));
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="260" height="140"><g transform="rotate(-24 130 70)" font-family="Inter,Arial,sans-serif" font-weight="700" font-size="13">`
            + `<text x="6" y="76" fill="rgba(11,42,74,.35)">${t}</text><text x="5" y="75" fill="rgba(255,255,255,.55)">${t}</text></g></svg>`;
        return `url("data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}")`;
    };
    const guard = el => { if (el.dataset.mark && !el.style.backgroundImage) el.style.backgroundImage = tile(el.dataset.mark); };

    const openPlayer = box => {
        const src = box.dataset.player, g = box.querySelector('.mat-thumb-guard');
        const wrap = document.createElement('div');
        wrap.className = 'media-modal';
        wrap.innerHTML = '<div class="media-modal-box"><button type="button" class="media-modal-close" aria-label="×">×</button>'
            + '<div class="media-modal-stage"><video controls autoplay playsinline disablepictureinpicture controlslist="nodownload noplaybackrate noremoteplayback"></video></div></div>';
        const v = wrap.querySelector('video'); v.src = src;
        if (g) { const ov = document.createElement('span'); ov.className = 'media-modal-guard'; ov.dataset.mark = g.dataset.mark; guard(ov); wrap.querySelector('.media-modal-stage').appendChild(ov); }
        wrap.addEventListener('contextmenu', e => e.preventDefault());
        const close = () => { v.pause(); v.removeAttribute('src'); v.load(); wrap.remove(); document.removeEventListener('keydown', esc); };
        const esc = e => e.key === 'Escape' && close();
        wrap.addEventListener('click', e => { if (e.target === wrap || e.target.classList.contains('media-modal-close')) close(); });
        document.addEventListener('keydown', esc);
        document.body.appendChild(wrap);
    };

    // Kadr dla starszych filmów: robiony z wczytanej miniatury i zapisywany (tylko zespół).
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
    const makePoster = v => {
        const url = v.dataset.makePoster; if (!url) return;
        delete v.dataset.makePoster;
        const grab = () => {
            try {
                const scale = Math.min(1, 960 / Math.max(v.videoWidth, v.videoHeight));
                const c = document.createElement('canvas');
                c.width = Math.round(v.videoWidth * scale); c.height = Math.round(v.videoHeight * scale);
                if (!c.width) return;
                c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
                c.toBlob(b => { if (!b) return; const fd = new FormData(); fd.append('poster', b, 'poster.jpg');
                    fetch(url, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, credentials: 'same-origin' }).catch(() => {}); }, 'image/jpeg', 0.82);
            } catch (e) { /* nic — zostaje klatka z filmu */ }
        };
        v.readyState >= 2 ? grab() : v.addEventListener('loadeddata', grab, { once: true });
    };

    const scan = root => {
        root.querySelectorAll('.mat-thumb-guard[data-mark]').forEach(guard);
        root.querySelectorAll('.mat-thumb video[data-src]').forEach(v => {
            // Z kadrem film ładuje się dopiero po najechaniu; bez kadru — gdy widoczny (i wtedy zespół zapisuje kadr).
            if (!v.hasAttribute('data-hover-only')) { io ? io.observe(v) : load(v); }
            if (v.dataset.makePoster) v.addEventListener('loadeddata', () => makePoster(v), { once: true });
            v.addEventListener('playing', () => v.closest('.mat-thumb')?.classList.add('is-playing'));
            v.addEventListener('pause', () => v.closest('.mat-thumb')?.classList.remove('is-playing'));
            const box = v.closest('.mat-thumb');
            if (box.dataset.bound) return; box.dataset.bound = 1;
            box.addEventListener('mouseenter', () => { load(v); v.play().catch(() => {}); });
            box.addEventListener('mouseleave', () => { v.pause(); try { v.currentTime = 0.8; } catch (e) {} });
        });
        root.querySelectorAll('.mat-thumb[data-player]').forEach(box => {
            if (box.dataset.pbound) return; box.dataset.pbound = 1;
            box.addEventListener('click', e => { e.preventDefault(); openPlayer(box); });
            box.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPlayer(box); } });
        });
        root.querySelectorAll('.mat-thumb.is-protected img').forEach(i => i.addEventListener('dragstart', e => e.preventDefault()));
    };
    scan(document);
    window.ninimmaThumbs = scan;
})();
