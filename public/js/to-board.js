// „Na tablicę”: grafika albo stop-klatki z filmu → tablica klienta (kopie ze znakiem wodnym robi serwer).
(function () {
    const cfgEl = document.getElementById('to-board-cfg');
    if (!cfgEl) return;
    const t = JSON.parse(cfgEl.dataset.i18n);
    const token = document.querySelector('meta[name=csrf-token]').content;
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const MAX = Number(cfgEl.dataset.max || 6);
    const clock = s => Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0');

    document.addEventListener('click', async e => {
        const btn = e.target.closest('[data-to-board]');
        if (!btn) return;
        e.preventDefault(); e.stopPropagation();
        btn.disabled = true;
        let d;
        try { d = await (await fetch(btn.dataset.toBoard, { headers: { Accept: 'application/json' } })).json(); } catch (err) { btn.disabled = false; return; }
        btn.disabled = false;
        open(btn.dataset.toBoard, d);
    });

    function open(url, d) {
        const frames = [];
        const dlg = document.createElement('dialog');
        dlg.className = 'tb-dialog';
        dlg.innerHTML = '<form method="dialog" class="tb-head"><div><strong>' + esc(t.snap_title) + ': ' + esc(d.client) + '</strong><small class="muted">' + esc(d.title) + '</small></div>'
            + '<button class="chat-icon-btn" aria-label="' + esc(t.snap_close) + '">✕</button></form>'
            + '<div class="tb-body"><div class="tb-media">'
            + (d.kind === 'video'
                ? '<video src="' + esc(d.src) + '" controls playsinline preload="metadata"></video><div class="tb-capture"><button type="button" class="btn btn-send btn-small" data-cap>📷 ' + esc(t.snap_capture) + '</button><small class="muted">' + esc(t.snap_frames_hint.replace(':n', MAX)) + '</small></div><div class="tb-frames" data-frames></div>'
                : '<img src="' + esc(d.src) + '" alt="">')
            + '</div><div class="tb-side"><p class="muted small">' + esc(t.snap_hint) + '</p>'
            + '<label class="field"><span>' + esc(t.snap_target) + '</span><select data-target><option value="">➕ ' + esc(t.snap_new) + '</option>'
            + d.boards.map(b => '<option value="' + b.id + '">' + esc(b.name) + ' — ' + esc(b.ticket) + '</option>').join('') + '</select></label>'
            + '<label class="field" data-title-wrap><span>' + esc(t.snap_new_title) + '</span><input data-title maxlength="200" value="' + esc(d.new_title) + '"></label>'
            + '<label class="field"><span>' + esc(t.snap_note) + '</span><textarea data-note rows="3" placeholder="' + esc(t.snap_note_ph) + '"></textarea></label>'
            + '<label class="check small"><input type="checkbox" data-ask checked> ' + esc(t.snap_ask) + '</label>'
            + '<p class="tb-error" data-error hidden></p>'
            + '<button type="button" class="btn btn-send" data-send>' + esc(t.snap_send) + '</button></div></div>';
        document.body.appendChild(dlg);
        dlg.addEventListener('close', () => dlg.remove());
        dlg.showModal();
        const $ = s => dlg.querySelector(s);
        $('[data-target]').addEventListener('change', e => { $('[data-title-wrap]').hidden = !!e.target.value; });
        const video = $('video');
        const renderFrames = () => {
            $('[data-frames]').innerHTML = frames.map((f, i) => '<span class="tb-frame"><img src="' + f.data + '" alt=""><small>' + clock(f.time) + '</small><button type="button" data-rm="' + i + '" aria-label="' + esc(t.snap_remove) + '">✕</button></span>').join('');
        };
        if (video) {
            $('[data-cap]').addEventListener('click', () => {
                if (!video.videoWidth || frames.length >= MAX) return;
                video.pause();
                const scale = Math.min(1, 1920 / Math.max(video.videoWidth, video.videoHeight));
                const c = document.createElement('canvas');
                c.width = Math.round(video.videoWidth * scale); c.height = Math.round(video.videoHeight * scale);
                c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
                try { frames.push({ data: c.toDataURL('image/jpeg', .86), time: video.currentTime }); } catch (err) { return; }
                renderFrames();
            });
            $('[data-frames]').addEventListener('click', e => { const b = e.target.closest('[data-rm]'); if (b) { frames.splice(Number(b.dataset.rm), 1); renderFrames(); } });
        }
        $('[data-send]').addEventListener('click', async e => {
            const err = $('[data-error]'); err.hidden = true;
            if (d.kind === 'video' && !frames.length) { err.textContent = t.snap_need_frame; err.hidden = false; return; }
            const b = e.currentTarget; b.disabled = true; b.textContent = t.snap_sending;
            const body = { board_id: $('[data-target]').value || null, title: $('[data-title]').value, note: $('[data-note]').value, ask: $('[data-ask]').checked, frames };
            try {
                const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify(body) });
                const j = await r.json();
                if (!r.ok) throw new Error(j.message || r.status);
                b.textContent = j.message; location.href = j.url;
            } catch (x) { err.textContent = x.message; err.hidden = false; b.disabled = false; b.textContent = t.snap_send; }
        });
    }
})();
