const previewRequests = new WeakMap();

export function characterLayout(images) {
    const width = Math.max(...images.map((image) => image.naturalWidth));
    let height = 0;
    const pieces = images.map((image) => {
        const piece = { image, x: Math.floor((width - image.naturalWidth) / 2), y: height };
        height += image.naturalHeight;
        return piece;
    });
    return { width, height, pieces };
}

export async function renderGameDataPreview(container, preview, name = '') {
    const request = {};
    previewRequests.set(container, request);
    container.replaceChildren();
    if (!preview) return;
    const doc = container.ownerDocument;
    const addImage = (url, label, fallback) => {
        if (typeof url !== 'string' || !/^(\/(?!\/)|https?:\/\/)/i.test(url)) return null;
        const figure = doc.createElement('figure');
        const image = doc.createElement('img');
        image.src = url;
        image.alt = label;
        image.tabIndex = 0;
        image.setAttribute('role', 'button');
        image.className = 'max-h-64 max-w-full cursor-zoom-in object-contain [image-rendering:pixelated]';
        image.dataset.gameDataImage = '';
        if (fallback) image.dataset.gameDataFallback = fallback;
        figure.appendChild(image);
        const caption = doc.createElement('figcaption');
        caption.textContent = label;
        caption.className = 'mt-2 text-xs text-slate-500';
        figure.appendChild(caption);
        container.appendChild(figure);
        initializeGameDataImages(figure);
        return image;
    };
    addImage(preview.image, name, preview.fallback);
    const labels = { head: 'Đầu', body: 'Thân', leg: 'Chân' };
    const parts = Object.entries(preview.parts || {});
    if (!parts.length) return;
    const images = parts.map(([part, asset]) => addImage(asset.image, `${labels[part] || part} #${asset.id}`));
    if (images.some((image) => !image)) {
        const message = doc.createElement('p');
        message.textContent = 'Chưa đủ ảnh đầu, thân, chân để ghép nhân vật.';
        message.className = 'w-full text-sm text-slate-500';
        container.appendChild(message);
        return;
    }
    try {
        await Promise.all(images.map((image) => image.decode()));
        if (previewRequests.get(container) !== request) return;
        const layout = characterLayout(images);
        if (layout.width * layout.height > 16000000) return;
        const canvas = doc.createElement('canvas');
        canvas.width = layout.width;
        canvas.height = layout.height;
        const context = canvas.getContext('2d');
        context.imageSmoothingEnabled = false;
        layout.pieces.forEach(({ image, x, y }) => context.drawImage(image, x, y));
        const composed = doc.createElement('img');
        composed.src = canvas.toDataURL('image/png');
        composed.alt = `${name} · Ghép đầu / thân / chân`;
        composed.tabIndex = 0;
        composed.setAttribute('role', 'button');
        composed.className = 'max-h-64 max-w-full cursor-zoom-in [image-rendering:pixelated]';
        container.prepend(composed);
    } catch {
        if (previewRequests.get(container) !== request) return;
        const message = doc.createElement('p');
        message.textContent = 'Không tải được đủ ảnh để ghép nhân vật.';
        message.setAttribute('role', 'status');
        container.appendChild(message);
    }
}

function initializeGameDataPreviews(root) {
    if (!root.querySelector('[data-game-data-preview], [data-game-data-page]')) return;
    root.querySelectorAll('[data-game-data-preview][data-preview]').forEach((container) => {
        void renderGameDataPreview(container, JSON.parse(container.dataset.preview), container.dataset.name);
    });
    if (root.documentElement.dataset.gameDataLightbox) return;
    root.documentElement.dataset.gameDataLightbox = 'true';
    const viewer = root.createElement('dialog');
    viewer.className = 'm-auto max-h-[95dvh] max-w-[95vw] overflow-auto rounded-xl bg-slate-950 p-4 text-white backdrop:bg-slate-950/80';
    viewer.setAttribute('aria-label', 'Xem ảnh lớn');
    const close = root.createElement('button');
    close.type = 'button';
    close.textContent = 'Đóng (Esc)';
    close.className = 'sticky top-0 mb-3 block min-h-11 rounded-lg bg-white px-4 font-bold text-slate-900';
    const image = root.createElement('img');
    image.className = 'max-w-none [image-rendering:pixelated]';
    const controls = root.createElement('div');
    controls.className = 'sticky top-0 mb-3 flex gap-2';
    controls.appendChild(close);
    let scale = 1;
    let previousOverflow = '';
    const zoom = (nextScale) => {
        scale = Math.min(8, Math.max(0.25, nextScale));
        image.style.width = `${Math.round(image.naturalWidth * scale)}px`;
    };
    image.addEventListener('load', () => zoom(scale));
    for (const [label, change] of [
        ['−', -0.5],
        ['+', 0.5],
    ]) {
        const button = root.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.setAttribute('aria-label', change > 0 ? 'Phóng to' : 'Thu nhỏ');
        button.className = 'min-h-11 min-w-11 rounded-lg bg-white px-4 font-bold text-slate-900';
        button.addEventListener('click', () => zoom(scale + change));
        controls.appendChild(button);
    }
    viewer.append(controls, image);
    root.body.appendChild(viewer);
    close.addEventListener('click', () => viewer.close());
    viewer.addEventListener('close', () => {
        root.documentElement.style.overflow = previousOverflow;
    });
    viewer.addEventListener('click', (event) => {
        if (event.target === viewer) viewer.close();
    });
    const open = (target) => {
        if (!(target instanceof Element)) return;
        const media = target.closest('[data-game-data-preview] img, [data-game-effect] canvas');
        if (!media) return;
        image.style.width = '';
        scale = 1;
        image.src = media.tagName === 'CANVAS' ? media.toDataURL('image/png') : media.currentSrc || media.src;
        image.alt = media.alt || 'Ảnh game';
        if (typeof viewer.showModal === 'function') {
            previousOverflow = root.documentElement.style.overflow;
            root.documentElement.style.overflow = 'hidden';
            viewer.showModal();
        }
    };
    root.addEventListener('click', (event) => open(event.target));
    root.addEventListener('keydown', (event) => {
        if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('[data-game-data-preview] img')) {
            event.preventDefault();
            open(event.target);
        }
    });
}

export function initializeGameDataImages(root = document) {
    root.querySelectorAll('[data-game-data-image]').forEach((image) => {
        if (image.dataset.gameDataImageInitialized) return;
        image.dataset.gameDataImageInitialized = 'true';
        const showAlternative = () => {
            const alternative = image.dataset.gameDataFallback;
            if (alternative) {
                delete image.dataset.gameDataFallback;
                image.src = alternative;
            } else {
                image.dataset.gameDataImageFailed = 'true';
                image.hidden = true;
            }
        };
        image.addEventListener('error', showAlternative);
        if (image.complete && image.naturalWidth === 0) showAlternative();
    });
}

export function initializeGameDataPage(root = document) {
    initializeGameDataImages(root);
    initializeGameDataPreviews(root);
    const page = root.querySelector('[data-game-data-page]');
    if (!page) return;
    const category = page.querySelector('[data-game-data-category]');
    category?.addEventListener('change', () => category.form.requestSubmit());
    const dialog = page.querySelector('[data-game-data-modal]');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const title = dialog.querySelector('[data-game-data-modal-title]');
    const code = dialog.querySelector('[data-game-data-json]');
    const loading = dialog.querySelector('[data-game-data-loading]');
    const error = dialog.querySelector('[data-game-data-error]');
    const copyJson = dialog.querySelector('[data-game-data-copy-json]');
    const pageLink = dialog.querySelector('[data-game-data-page-link]');
    const preview = dialog.querySelector('[data-game-data-preview]');
    const feedback = page.querySelector('[data-game-data-feedback]');
    let opener = null;
    let pending = null;
    let sequence = 0;
    let previousOverflow = '';
    let json = '';

    async function copy(value) {
        try {
            await navigator.clipboard.writeText(value);
            if (feedback) feedback.textContent = 'Đã sao chép vào bộ nhớ tạm.';
            return true;
        } catch {
            if (feedback) feedback.textContent = 'Không sao chép được. Bạn có thể chọn và sao chép nội dung JSON.';
            return false;
        }
    }

    page.querySelectorAll('[data-game-data-copy-id]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (await copy(button.dataset.gameDataCopyId)) {
                const label = button.querySelector('span');
                const original = label.textContent;
                label.textContent = 'Đã chép';
                window.setTimeout(() => (label.textContent = original), 1500);
            }
        });
    });
    page.querySelectorAll('[data-game-data-open]').forEach((link) => {
        link.addEventListener('click', async (event) => {
            if (event.button > 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            pending?.abort();
            pending = new AbortController();
            const request = ++sequence;
            opener = link;
            title.textContent = link.dataset.name;
            pageLink.href = link.href;
            loading.hidden = false;
            code.hidden = true;
            error.hidden = true;
            copyJson.disabled = true;
            json = '';
            preview?.replaceChildren();
            if (!dialog.open) {
                previousOverflow = root.documentElement.style.overflow;
                root.documentElement.style.overflow = 'hidden';
                dialog.showModal();
            }
            try {
                const response = await fetch(link.href, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: pending.signal,
                });
                if (!response.ok) throw new Error('Không tải được dữ liệu. Bạn có thể mở trang chi tiết để thử lại.');
                const payload = await response.json();
                if (request !== sequence || !dialog.open) return;
                if (!payload.data || typeof payload.data !== 'object') throw new Error('Dữ liệu phản hồi không hợp lệ.');
                json = JSON.stringify(payload.data, null, 2);
                code.textContent = json;
                if (preview) void renderGameDataPreview(preview, payload.preview, link.dataset.name);
                code.hidden = false;
                copyJson.disabled = false;
            } catch (failure) {
                if (request !== sequence || failure.name === 'AbortError') return;
                error.textContent = failure.message || 'Không tải được dữ liệu.';
                error.hidden = false;
            } finally {
                if (request === sequence) loading.hidden = true;
            }
        });
    });
    copyJson.addEventListener('click', () => {
        if (json !== '') void copy(json);
    });
    dialog.querySelector('[data-game-data-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)
            dialog.close();
    });
    dialog.addEventListener('close', () => {
        ++sequence;
        pending?.abort();
        root.documentElement.style.overflow = previousOverflow;
        opener?.focus({ preventScroll: true });
    });
}
