export function frameAtElapsed(data, elapsedMs) {
    const tick = Math.floor((Math.max(0, elapsedMs) * data.tickRate) / 1000);
    return data.sequence[tick % data.sequence.length];
}

export function drawEffectFrame(context, sheet, data, frameId) {
    const regions = new Map(data.regions.map((region) => [region.id, region]));
    context.imageSmoothingEnabled = false;
    context.clearRect(0, 0, data.width, data.height);
    for (const piece of data.frames[frameId].pieces) {
        const region = regions.get(piece.imageId);
        if (!region) throw new Error('Unknown effect region.');
        context.drawImage(
            sheet,
            region.x,
            region.y,
            region.width,
            region.height,
            data.originX + piece.dx,
            data.originY + piece.dy,
            region.width,
            region.height,
        );
    }
}

const assets = new Map();
function loadAssets(descriptor) {
    const key = `${descriptor.dataUrl}|${descriptor.sheetUrl}`;
    if (!assets.has(key)) {
        const sheet = new Image();
        const imageLoaded = new Promise((resolve, reject) => {
            sheet.onload = () => resolve(sheet);
            sheet.onerror = () => reject(new Error('Cannot load effect sheet.'));
        });
        sheet.src = descriptor.sheetUrl;
        const dataLoaded = fetch(descriptor.dataUrl).then((response) => {
            if (!response.ok) throw new Error('Cannot load effect animation.');
            return response.json();
        });
        assets.set(
            key,
            Promise.all([imageLoaded, dataLoaded]).catch((error) => {
                assets.delete(key);
                throw error;
            }),
        );
    }
    return assets.get(key);
}

export async function mountEffect(element) {
    const status = element.querySelector('[data-effect-status]');
    const fallback = element.querySelector('[data-effect-fallback]');
    let observer;
    let animation = null;
    let visibilityChanged;
    try {
        const descriptor = JSON.parse(element.dataset.gameEffect);
        if (!descriptor.sheetUrl || !descriptor.dataUrl) return;
        const [sheet, data] = await loadAssets(descriptor);
        if (!element.isConnected) return;
        if (
            !Number.isInteger(data.width) ||
            !Number.isInteger(data.height) ||
            data.width < 1 ||
            data.height < 1 ||
            data.width > 2048 ||
            data.height > 2048 ||
            !Number.isFinite(data.tickRate) ||
            data.tickRate < 1 ||
            data.tickRate > 120 ||
            !Array.isArray(data.sequence) ||
            data.sequence.length === 0 ||
            data.sequence.some((id) => !Number.isInteger(id) || !data.frames[id])
        )
            throw new Error('Invalid animation.');
        for (const region of data.regions) {
            if (
                region.x < 0 ||
                region.y < 0 ||
                region.width < 1 ||
                region.height < 1 ||
                region.x + region.width > sheet.naturalWidth ||
                region.y + region.height > sheet.naturalHeight
            )
                throw new Error('Invalid region.');
        }
        const canvas = element.querySelector('canvas');
        const context = canvas.getContext('2d');
        if (!context) throw new Error('Canvas unavailable.');
        canvas.width = data.width;
        canvas.height = data.height;
        drawEffectFrame(context, sheet, data, data.sequence[0]);
        canvas.hidden = false;
        if (fallback) fallback.hidden = true;
        status.hidden = true;
        const start = performance.now();
        let lastFrame = data.sequence[0];
        let visible = true;
        const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
        const stop = () => {
            if (animation !== null) cancelAnimationFrame(animation);
            animation = null;
        };
        const render = (now) => {
            animation = null;
            if (!element.isConnected) {
                observer?.disconnect();
                document.removeEventListener('visibilitychange', visibilityChanged);
                return;
            }
            if (!visible || document.hidden || reducedMotion) return;
            const frame = frameAtElapsed(data, now - start);
            if (frame !== lastFrame) {
                drawEffectFrame(context, sheet, data, frame);
                lastFrame = frame;
            }
            animation = requestAnimationFrame(render);
        };
        const resume = () => {
            if (animation === null && visible && !document.hidden && !reducedMotion) animation = requestAnimationFrame(render);
        };
        visibilityChanged = () => (document.hidden ? stop() : resume());
        document.addEventListener('visibilitychange', visibilityChanged);
        if ('IntersectionObserver' in window) {
            observer = new IntersectionObserver(([entry]) => {
                visible = entry.isIntersecting;
                if (visible) resume();
                else stop();
            });
            observer.observe(element);
        }
        resume();
    } catch {
        if (animation !== null) cancelAnimationFrame(animation);
        observer?.disconnect();
        if (visibilityChanged) document.removeEventListener('visibilitychange', visibilityChanged);
        element.querySelector('canvas').hidden = true;
        if (fallback && !fallback.dataset.gameDataImageFailed) fallback.hidden = false;
        status.hidden = false;
        status.textContent = 'Không tải được hiệu ứng.';
    }
}

export function initializeGameEffects(root = document) {
    for (const element of root.querySelectorAll('[data-game-effect]')) {
        if (element.dataset.effectInitialized) continue;
        element.dataset.effectInitialized = 'true';
        void mountEffect(element);
    }
}
