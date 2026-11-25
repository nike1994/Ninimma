/**
 * Załączniki: wybór pliku, przeciągnięcie, wklejenie zrzutu ze schowka, nagranie ekranu.
 * Pliki wysyłane w kawałkach; obrazy kompresowane w przeglądarce.
 */
(function () {
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
    const MAX_IMAGE_EDGE = 2400;

    async function compressImage(file) {
        if (!/^image\/(png|jpeg|webp)$/.test(file.type) || file.size < 400 * 1024) return file;
        try {
            const bmp = await createImageBitmap(file);
            const scale = Math.min(1, MAX_IMAGE_EDGE / Math.max(bmp.width, bmp.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(bmp.width * scale); canvas.height = Math.round(bmp.height * scale);
            canvas.getContext('2d').drawImage(bmp, 0, 0, canvas.width, canvas.height);
            const type = file.type === 'image/png' && scale === 1 ? 'image/png' : 'image/jpeg';
            const blob = await new Promise(r => canvas.toBlob(r, type, 0.85));
            if (!blob || blob.size >= file.size) return file;
            const name = file.name.replace(/\.(png|webp)$/i, type === 'image/jpeg' ? '.jpg' : '.$1');
            return new File([blob], name, { type });
        } catch (e) { return file; }
    }

    async function post(url, body) {
        const res = await fetch(url, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, credentials: 'same-origin' });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || ('HTTP ' + res.status));
        return data;
    }

    async function uploadFile(startUrl, file, onProgress) {
        const start = new FormData();
        start.append('name', file.name); start.append('size', file.size); start.append('mime', file.type || '');
        const session = await post(startUrl, start);
        const base = startUrl.replace(/\/$/, '') + '/' + session.id;
        for (let i = 0; i < session.total_chunks; i++) {
            const part = new FormData();
            part.append('index', i);
            part.append('chunk', file.slice(i * session.chunk_size, (i + 1) * session.chunk_size), 'chunk');
            let attempt = 0;
            for (;;) {
                try { await post(base + '/chunk', part); break; } catch (e) { if (++attempt >= 3) throw e; await new Promise(r => setTimeout(r, 1000 * attempt)); }
            }
            onProgress((i + 1) / session.total_chunks);
        }
        return post(base + '/finish', new FormData());
    }

    function initUploader(root) {
        const form = root.closest('form');
        const list = root.querySelector('.uploader-list');
        const input = root.querySelector('input[type=file]');
        const maxMb = Number(root.dataset.maxMb || 0);
        const maxRec = Number(root.dataset.maxRec || 0);
        let pending = 0;

        const add = async (raw) => {
            const file = await compressImage(raw);
            const item = document.createElement('div');
            item.className = 'upload-item';
            item.innerHTML = '<span class="name"></span> <progress max="1" value="0"></progress> <button type="button" class="link">✕</button>';
            item.querySelector('.name').textContent = file.name;
            list.appendChild(item);
            item.querySelector('button').onclick = () => item.remove();
            if (maxMb && file.size > maxMb * 1024 * 1024) {
                item.classList.add('error'); item.querySelector('progress').replaceWith(Object.assign(document.createElement('small'), { textContent: '> ' + maxMb + ' MB' }));
                return;
            }
            pending++; form.dataset.pending = pending;
            try {
                const res = await uploadFile(root.dataset.start, file, p => item.querySelector('progress').value = p);
                const hidden = Object.assign(document.createElement('input'), { type: 'hidden', name: 'uploads[]', value: res.usage_id });
                item.appendChild(hidden);
                item.classList.add('done');
            } catch (e) {
                item.classList.add('error');
                item.querySelector('progress')?.replaceWith(Object.assign(document.createElement('small'), { textContent: e.message }));
            }
            pending--; form.dataset.pending = pending;
            form.dispatchEvent(new CustomEvent('uploads-changed'));
        };

        input.addEventListener('change', () => { [...input.files].forEach(add); input.value = ''; });
        form.addEventListener('dragover', e => { e.preventDefault(); form.classList.add('dragging'); });
        form.addEventListener('dragleave', () => form.classList.remove('dragging'));
        form.addEventListener('drop', e => { e.preventDefault(); form.classList.remove('dragging'); [...e.dataTransfer.files].forEach(add); });
        form.querySelectorAll('[data-paste-target]').forEach(t => t.addEventListener('paste', e => {
            const files = [...(e.clipboardData?.files || [])];
            if (files.length) {
                e.preventDefault();
                files.forEach((f, i) => add(f.name && f.name !== 'image.png' ? f : new File([f], 'zrzut-' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + (i ? '-' + i : '') + '.png', { type: f.type })));
            }
        }));

        // Nagrywanie ekranu (getDisplayMedia + MediaRecorder), z limitem czasu dla klienta.
        const recBtn = root.querySelector('[data-record]');
        if (!navigator.mediaDevices?.getDisplayMedia || !window.MediaRecorder) { recBtn.hidden = true; return; }
        let recorder = null, timer = null;
        recBtn.addEventListener('click', async () => {
            if (recorder) { recorder.stop(); return; }
            let stream;
            try { stream = await navigator.mediaDevices.getDisplayMedia({ video: { frameRate: 15 }, audio: false }); } catch (e) { return; }
            const mime = ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm', 'video/mp4'].find(t => MediaRecorder.isTypeSupported(t)) || '';
            const chunks = [];
            recorder = new MediaRecorder(stream, mime ? { mimeType: mime, videoBitsPerSecond: 1_500_000 } : {});
            recorder.ondataavailable = e => e.data.size && chunks.push(e.data);
            recorder.onstop = () => {
                clearInterval(timer); stream.getTracks().forEach(t => t.stop());
                recBtn.textContent = recBtn.dataset.label; recorder = null;
                const type = (mime || 'video/webm').split(';')[0];
                add(new File([new Blob(chunks, { type })], 'nagranie-' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + (type === 'video/mp4' ? '.mp4' : '.webm'), { type }));
            };
            stream.getVideoTracks()[0].addEventListener('ended', () => recorder?.state === 'recording' && recorder.stop());
            recBtn.dataset.label = recBtn.dataset.label || recBtn.textContent;
            const started = Date.now();
            timer = setInterval(() => {
                const s = Math.floor((Date.now() - started) / 1000);
                recBtn.textContent = '⏹ ' + Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') + (maxRec ? ' / ' + Math.floor(maxRec / 60) + ':' + String(maxRec % 60).padStart(2, '0') : '');
                if (maxRec && s >= maxRec) recorder.stop();
            }, 500);
            recorder.start(1000);
        });
    }

    document.querySelectorAll('[data-uploader]').forEach(initUploader);
    // Blokada wysłania formularza, dopóki trwa wysyłka plików.
    document.querySelectorAll('form[data-message-form]').forEach(form => form.addEventListener('submit', e => {
        if (Number(form.dataset.pending || 0) > 0) { e.preventDefault(); alert('…'); }
    }));
})();
