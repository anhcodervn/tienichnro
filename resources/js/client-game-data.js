export function initializeGameDataPage(root = document) {
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
    page.querySelectorAll('[data-game-data-image]').forEach((image) => {
        image.addEventListener('error', () => (image.hidden = true));
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
