export const minImageZoom = 0.5;
export const maxImageZoom = 8;

export function zoomImage(state, factor) {
    state.scale = Math.min(maxImageZoom, Math.max(minImageZoom, state.scale * factor));
}

export function startImageDrag(state, event, viewport) {
    if (event.button !== 0) return;
    state.drag = { x: event.clientX, y: event.clientY, left: viewport.scrollLeft, top: viewport.scrollTop };
    viewport.setPointerCapture(event.pointerId);
}

export function moveImageDrag(state, event, viewport) {
    if (!state.drag) return;
    viewport.scrollLeft = state.drag.left - (event.clientX - state.drag.x);
    viewport.scrollTop = state.drag.top - (event.clientY - state.drag.y);
}

export function stopImageDrag(state) {
    state.drag = null;
}
