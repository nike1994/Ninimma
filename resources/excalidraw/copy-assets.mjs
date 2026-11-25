// Kopiuje czcionki Excalidraw do public/vendor/excalidraw/fonts (EXCALIDRAW_ASSET_PATH).
// Pomijamy Xiaolai (znaki chińskie) — zbędne dla PL/EN, oszczędza ~10 MB na hostingu.
import { cpSync, existsSync } from 'node:fs';
const src = 'node_modules/@excalidraw/excalidraw/dist/prod/fonts';
if (existsSync(src)) {
    cpSync(src, '../../public/vendor/excalidraw/fonts', { recursive: true, filter: (p) => !p.includes('Xiaolai') });
}
