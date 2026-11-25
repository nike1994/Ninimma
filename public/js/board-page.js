/**
 * Tablica: Excalidraw + synchronizacja przez odpytywanie co 2–3 s.
 * Wysyłane są tylko zmienione elementy (porównanie numeru wersji elementu);
 * elementy drugiej strony są przy pierwszym wczytaniu blokowane, żeby nie przesunąć ich przypadkiem.
 */
const cfg = window.BOARD;
const csrf = document.querySelector('meta[name=csrf-token]')?.content;
const lib = await import(cfg.lib);
const NEVER = lib.CaptureUpdateAction?.NEVER ?? 'NEVER';
const el = document.getElementById('board');
const state = document.querySelector('[data-sync-state]');
const presenceEl = document.querySelector('[data-presence]');
const mobile = window.matchMedia('(max-width: 768px)').matches;
const editable = cfg.canEdit && !mobile;
const EMBEDS = /^(https?:\/\/)?([\w-]+\.)?(youtube\.com|youtu\.be|vimeo\.com|canva\.com|facebook\.com|fb\.watch|instagram\.com|figma\.com|loom\.com|docs\.google\.com|google\.com\/maps|tiktok\.com)\//i;

let api = null, since = 0, pushing = false, dirty = false, timer = null, pollTimer = null;
const sent = {}, known = new Set(), loadedFiles = new Set(), uploaded = new Set();

const setState = (k) => { if (state && cfg.labels[k]) state.textContent = cfg.labels[k]; };
const lockForeign = (e) => {
    if (known.has(e.id)) return e;
    known.add(e.id);
    return e.customData?.side && e.customData.side !== cfg.side && !e.locked ? { ...e, locked: true } : e;
};

async function request(url, options = {}) {
    const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, ...options });
    if (!res.ok) throw new Error(res.status);
    return res.json();
}

async function loadFiles(files) {
    for (const f of files || []) {
        if (loadedFiles.has(f.id)) continue;
        loadedFiles.add(f.id); uploaded.add(f.id);
        const blob = await (await fetch(f.url, { credentials: 'same-origin' })).blob();
        const dataURL = await new Promise(r => { const fr = new FileReader(); fr.onload = () => r(fr.result); fr.readAsDataURL(blob); });
        api?.addFiles([{ id: f.id, mimeType: f.mimeType, dataURL, created: Date.now() }]);
    }
}

function apply(data) {
    since = Math.max(since, data.revision || 0);
    if (presenceEl) presenceEl.innerHTML = (data.presence || []).map(n => `<span class="avatar avatar-v${n.length % 4}" style="--s:28px" title="${n.replace(/"/g, '')}">${n.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()}</span>`).join('');
    loadFiles(data.files);
    if (!api || !data.elements?.length) return;
    const map = new Map(api.getSceneElementsIncludingDeleted().map(e => [e.id, e]));
    let changed = false;
    for (const remote of data.elements) {
        const local = map.get(remote.id);
        sent[remote.id] = Math.max(sent[remote.id] ?? -1, remote.version);
        if (!local || local.version < remote.version) { map.set(remote.id, lockForeign(remote)); changed = true; }
    }
    if (changed) api.updateScene({ elements: [...map.values()], captureUpdate: NEVER });
}

async function push() {
    if (!api || pushing || !editable) return;
    const changed = api.getSceneElementsIncludingDeleted().filter(e => (sent[e.id] ?? -1) < e.version);
    const files = api.getFiles();
    for (const id of Object.keys(files)) {
        if (!uploaded.has(id) && files[id].dataURL?.startsWith('data:image')) {
            uploaded.add(id);
            request(cfg.files, { method: 'POST', body: JSON.stringify({ id, dataURL: files[id].dataURL }) }).catch(() => uploaded.delete(id));
        }
    }
    if (!changed.length) { dirty = false; return; }
    pushing = true; setState('saving');
    changed.forEach(e => { sent[e.id] = e.version; known.add(e.id); });
    try {
        apply(await request(cfg.sync, { method: 'POST', body: JSON.stringify({ elements: changed, since }) }));
        dirty = false; setState('saved');
    } catch (e) {
        changed.forEach(c => { sent[c.id] = -1; }); setState('offline');
    }
    pushing = false;
}

async function poll() {
    clearTimeout(pollTimer);
    if (!document.hidden && !pushing) {
        try { apply(await request(cfg.sync + '?since=' + since)); } catch (e) { setState('offline'); }
    }
    pollTimer = setTimeout(poll, dirty ? 1500 : 2500);
}

// Stan początkowy.
let initial = [];
if (cfg.snapshot) {
    initial = cfg.snapshot;
} else {
    const data = await request(cfg.sync + '?since=0');
    since = data.revision;
    initial = data.elements.map(e => { sent[e.id] = e.version; return lockForeign(e); });
    setTimeout(() => loadFiles(data.files), 300);
}
if (cfg.snapshot) {
    request(cfg.sync + '?since=0').then(d => setTimeout(() => loadFiles(d.files), 300));
    // Wersja bez obrazu podglądu: generujemy go przy pierwszym obejrzeniu.
    if (cfg.previewUrl) setTimeout(async () => {
        const svg = await previewSvg(cfg.snapshot);
        if (svg) request(cfg.previewUrl, { method: 'POST', body: JSON.stringify({ preview: svg }) }).catch(() => {});
    }, 2500);
}

lib.mount(el, {
    excalidrawAPI: (a) => { api = a; },
    initialData: { elements: initial, appState: { viewBackgroundColor: '#ffffff' }, scrollToContent: true },
    viewModeEnabled: !editable,
    langCode: cfg.lang,
    validateEmbeddable: (url) => EMBEDS.test(url) || undefined,
    UIOptions: { canvasActions: { loadScene: false, saveToActiveFile: false, toggleTheme: false } },
    onChange: () => {
        if (!editable || !api) return;
        dirty = true;
        clearTimeout(timer); timer = setTimeout(push, 500);
    },
});
if (!editable && cfg.canEdit && mobile) setState('viewOnly');

if (!cfg.snapshot) {
    poll();
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    // Wersja po zakończeniu sesji edycji.
    window.addEventListener('pagehide', () => {
        if (!editable) return;
        const fd = new FormData(); fd.append('_token', csrf);
        if (lastPreview && lastPreview.length < 55000) fd.append('preview', lastPreview);
        navigator.sendBeacon(cfg.sessionEnd, fd);
    });
}

// Podgląd SVG bieżącej sceny (dla wersji i archiwum).
async function previewSvg(elements) {
    try {
        const svg = await lib.exportToSvg({ elements: elements ?? api.getSceneElements(), appState: { exportBackground: true, viewBackgroundColor: '#ffffff' },
            files: api?.getFiles() ?? {}, skipInliningFonts: true });
        return svg.outerHTML;
    } catch (e) { return null; }
}
let lastPreview = null;
if (!cfg.snapshot) setInterval(async () => { if (api && editable && document.visibilityState === 'visible') lastPreview = await previewSvg(); }, 20000);

// Zapis świadomej wersji.
document.querySelector('[data-save-version]')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    await push();
    const form = ev.target;
    await request(cfg.versions, { method: 'POST', body: JSON.stringify({ description: form.description.value, preview: await previewSvg() }) });
    location.reload();
});
// Panel wersji i zmian.
document.querySelector('[data-panel-toggle]')?.addEventListener('click', () => { const p = document.querySelector('[data-panel]'); p.hidden = !p.hidden; });
document.querySelectorAll('[data-goto]').forEach(b => b.addEventListener('click', () => {
    const target = api?.getSceneElements().find(e => e.id === b.dataset.goto);
    if (!target) return;
    api.scrollToContent(target, { fitToContent: false, animate: true });
    api.updateScene({ appState: { selectedElementIds: { [target.id]: true } }, captureUpdate: NEVER });
}));
document.querySelector('[data-mark-seen]')?.addEventListener('click', async (ev) => {
    await request(cfg.seen, { method: 'POST', body: '{}' });
    ev.target.remove();
    document.querySelector('[data-panel-toggle] .dot')?.remove();
});
