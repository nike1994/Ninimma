/**
 * Komunikacja "prawie na żywo".
 * Cała wymiana zdarzeń z serwerem przechodzi przez window.Live, żeby później
 * dało się podmienić PollingTransport na WebSocketTransport bez zmian w reszcie kodu.
 */
(function () {
    class PollingTransport {
        constructor({ url, activeMs = 3000, idleMs = 15000, idleAfterMs = 60000 }) {
            this.url = url; this.activeMs = activeMs; this.idleMs = idleMs; this.idleAfterMs = idleAfterMs;
            this.handlers = []; this.cursor = {}; this.timer = null; this.lastActivity = Date.now(); this.busy = false;
            ['mousemove', 'keydown', 'scroll', 'touchstart', 'focus'].forEach(ev =>
                window.addEventListener(ev, () => this.touch(), { passive: true }));
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) { this.stop(); } else { this.touch(); this.poll(); }
            });
        }
        onMessage(fn) { this.handlers.push(fn); }
        setCursor(cursor) { Object.assign(this.cursor, cursor); }
        touch() {
            const wasIdle = Date.now() - this.lastActivity > this.idleAfterMs;
            this.lastActivity = Date.now();
            if (wasIdle) this.schedule(0);
        }
        interval() { return Date.now() - this.lastActivity > this.idleAfterMs ? this.idleMs : this.activeMs; }
        start() { this.schedule(this.activeMs); }
        stop() { clearTimeout(this.timer); this.timer = null; }
        schedule(ms) { this.stop(); if (!document.hidden) this.timer = setTimeout(() => this.poll(), ms); }
        async poll() {
            if (this.busy) return;
            this.busy = true;
            try {
                const qs = new URLSearchParams(this.cursor).toString();
                const res = await fetch(this.url + (qs ? '?' + qs : ''), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (res.ok) { const data = await res.json(); this.handlers.forEach(h => h(data)); }
            } catch (e) { /* sieć chwilowo niedostępna — spróbujemy ponownie */ }
            this.busy = false;
            this.schedule(this.interval());
        }
        /** Wymuś natychmiastowe sprawdzenie (np. po wysłaniu wiadomości). */
        nudge() { this.touch(); this.schedule(0); }
    }

    window.Live = {
        connect(options) { return new PollingTransport(options); },
        csrf() { return document.querySelector('meta[name=csrf-token]')?.content; },
    };
})();
