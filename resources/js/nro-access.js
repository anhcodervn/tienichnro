import axios from 'axios';

export function initializeNroAccess(root = document, http = axios, browser = window) {
    const form = root.querySelector('[data-nro-access-form]');
    if (!form || form.dataset.initialized === 'true') return;
    form.dataset.initialized = 'true';
    const button = form.querySelector('[type="submit"]');
    const error = form.querySelector('[data-nro-access-error]');
    let pending = false;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (pending) return;
        const data = new FormData(form);
        if (!data.get('cf-turnstile-response')) {
            error.textContent = 'Vui lòng hoàn thành xác minh trước.';
            error.hidden = false;
            return;
        }
        pending = true;
        button.disabled = true;
        error.hidden = true;
        try {
            await http.post(form.action, data, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            browser.location.reload();
        } catch (failure) {
            const response = failure.response;
            error.textContent =
                Object.values(response?.data?.errors || {}).flat()[0] ||
                (response?.status === 429
                    ? 'Bạn xác minh quá nhiều lần. Vui lòng đợi rồi thử lại.'
                    : response?.data?.message || 'Không xác minh được. Vui lòng thử lại.');
            error.hidden = false;
            browser.turnstile?.reset();
        } finally {
            pending = false;
            button.disabled = false;
        }
    });
}
