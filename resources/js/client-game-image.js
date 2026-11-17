import { createScene, drawScene } from './game-image-scene.js';
export { itemCrop, loadCaptureImage, PANEL_FILES, parseItemIds } from './game-image-capture.js';

export function drawComposition(canvas, images, ids, showDetails) {
    drawScene(canvas, images, createScene(images, ids, showDetails));
    return { width: canvas.width, height: canvas.height };
}

export async function initializeGameImageComposer() {
    const root = document.querySelector('[data-game-image-editor]');
    if (!root) return;
    try {
        const [{ createApp }, { default: GameImageEditor }] = await Promise.all([
            import('vue'),
            import('./components/game-image/GameImageEditor.vue'),
        ]);
        createApp(GameImageEditor, {
            account: JSON.parse(root.dataset.account),
            demoBase: root.dataset.demoBase,
            indexUrl: root.dataset.indexUrl,
        }).mount(root);
    } catch {
        const status = root.querySelector('[role="status"]');
        if (status) status.textContent = 'Không tải được trình chỉnh ảnh. Hãy tải lại trang.';
    }
}
