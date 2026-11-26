// Miniatury filmów: kadr wczytuje się dopiero, gdy miniatura jest widoczna; po najechaniu film gra bez dźwięku.
(function () {
    const load = v => { if (!v.src && v.dataset.src) { v.preload = 'metadata'; v.src = v.dataset.src; } };
    const io = 'IntersectionObserver' in window ? new IntersectionObserver(es => es.forEach(e => {
        if (e.isIntersecting) { load(e.target); io.unobserve(e.target); }
    }), { rootMargin: '200px' }) : null;
    const scan = root => root.querySelectorAll('.mat-thumb video[data-src]').forEach(v => {
        io ? io.observe(v) : load(v);
        const box = v.closest('.mat-thumb');
        if (box.dataset.bound) return; box.dataset.bound = 1;
        box.addEventListener('mouseenter', () => { load(v); v.play().catch(() => {}); });
        box.addEventListener('mouseleave', () => { v.pause(); try { v.currentTime = 0.8; } catch (e) {} });
    });
    scan(document);
    window.ninimmaThumbs = scan;
})();
