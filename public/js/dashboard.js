/** Pulpit: podpowiedzi na wykresie i przypomnienie (toast) zamykane do końca dnia. */
(function () {
    const chart = document.querySelector('[data-chart]');
    if (chart) {
        const tip = chart.querySelector('.chart-tip');
        chart.querySelectorAll('.hover').forEach(g => {
            g.addEventListener('mouseenter', () => {
                tip.querySelector('strong').textContent = g.dataset.title;
                tip.querySelector('[data-m]').textContent = g.dataset.m;
                tip.querySelector('[data-a]').textContent = g.dataset.a;
                const box = chart.getBoundingClientRect(), r = g.querySelector('circle').getBoundingClientRect();
                tip.hidden = false;
                const left = Math.min(Math.max(r.left - box.left - tip.offsetWidth / 2, 0), box.width - tip.offsetWidth);
                tip.style.left = left + 'px';
                tip.style.top = Math.max(0, r.top - box.top - tip.offsetHeight - 14) + 'px';
                g.classList.add('on');
            });
            g.addEventListener('mouseleave', () => { tip.hidden = true; g.classList.remove('on'); });
        });
    }
    const toast = document.querySelector('[data-toast]');
    if (toast && !localStorage.getItem('toast:' + toast.dataset.toast)) {
        setTimeout(() => { toast.hidden = false; requestAnimationFrame(() => toast.classList.add('show')); }, 600);
        toast.querySelector('[data-toast-close]').addEventListener('click', () => {
            localStorage.setItem('toast:' + toast.dataset.toast, '1'); toast.remove();
        });
    }
})();
