// Stoper pracy: odliczanie od czasu startu zapisanego na serwerze (działa po odświeżeniu i na wielu kartach).
(function () {
    const els = document.querySelectorAll('[data-timer-since]');
    if (!els.length) return;
    const tick = () => {
        const now = Date.now() / 1000;
        els.forEach(el => {
            const s = Math.max(0, Math.floor(now - Number(el.dataset.timerSince)));
            const h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), sec = s % 60;
            el.textContent = h + ':' + String(m).padStart(2, '0') + (h ? '' : ':' + String(sec).padStart(2, '0'));
        });
    };
    tick();
    setInterval(tick, 1000);
})();
