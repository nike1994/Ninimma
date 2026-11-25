// Punkt wejścia paczki tablic: Excalidraw (MIT) + React, wystawione jako window.NinimmaBoard.
import React from 'react';
import { createRoot } from 'react-dom/client';
import { Excalidraw, exportToSvg, restoreElements, convertToExcalidrawElements, CaptureUpdateAction } from '@excalidraw/excalidraw';
import '@excalidraw/excalidraw/index.css';

export function mount(el, props) {
    const root = createRoot(el);
    root.render(React.createElement(Excalidraw, props));
    return root;
}

export { React, exportToSvg, restoreElements, convertToExcalidrawElements, CaptureUpdateAction };
