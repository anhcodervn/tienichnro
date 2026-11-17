export function initializeGamePassword(root = document, environment = window, request = fetch) {
    initializeGamePasswordCaptcha(root, request);
    const form = root.querySelector('[data-password-form]');
    if (form && !form.dataset.initialized) {
        form.dataset.initialized = 'true';
        const toggle = form.querySelector('[data-password-bulk-toggle]');
        const single = form.querySelector('[data-password-single]');
        const bulk = form.querySelector('[data-password-bulk]');
        const text = bulk.querySelector('textarea');
        const button = form.querySelector('button[type="submit"]');
        const label = button.querySelector('[data-password-submit-label]');
        let submitting = false;
        form.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-password-visibility]');
            if (!toggle) return;
            const input = form.querySelector(`#${toggle.dataset.passwordVisibility}`);
            if (!input || input.disabled) return;
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            const label = `${visible ? 'Ẩn' : 'Hiện'} ${toggle.dataset.passwordLabel}`;
            toggle.setAttribute('aria-pressed', String(visible));
            toggle.setAttribute('aria-label', label);
            toggle.title = label;
        });
        const update = () => {
            single.hidden = toggle.checked;
            bulk.hidden = !toggle.checked;
            single.querySelectorAll('input,select').forEach((field) => (field.disabled = toggle.checked));
            text.disabled = !toggle.checked;
            text.required = toggle.checked;
            const count = toggle.checked ? text.value.split(/\r?\n/).filter((line) => line.trim()).length : 1;
            const amount = Number(form.dataset.price) * count;
            button.disabled = submitting || count < 1 || count > 100 || form.dataset.price === '';
            label.textContent = submitting ? 'Đang gửi yêu cầu…' : `Đổi mật khẩu · ${count} tài khoản`;
            if (form.dataset.price === '') {
                form.querySelector('[data-password-total]').textContent = 'Ch\u01b0a c\u1ea5u h\u00ecnh gi\u00e1';
                return;
            }
            form.querySelector('[data-password-total]').textContent = `${count} tài khoản · Tổng phí ${amount.toLocaleString('vi-VN')} đ`;
        };
        toggle.addEventListener('change', update);
        text.addEventListener('input', update);
        form.addEventListener('submit', (event) => {
            if (event?.defaultPrevented) return;
            submitting = true;
            update();
        });
        environment.addEventListener('pageshow', () => {
            submitting = false;
            update();
        });
        update();
    }
    const section = root.querySelector('[data-password-history]');
    if (!section || section.dataset.initialized) return;
    section.dataset.initialized = 'true';
    const content = section.querySelector('[data-password-history-content]');
    const status = section.querySelector('[data-password-history-status]');
    const logModal = initializeGamePasswordLogModal(root, section, content);
    const updateHistory = (html) => {
        renderGamePasswordHistory(content, html);
        logModal.sync();
    };
    section.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-password-retry]');
        if (!button || button.disabled || !form) return;
        try {
            status.textContent = 'Đang đưa đơn vào hàng chờ…';
            await retrySavedGamePassword(button, form.querySelector('input[name="_token"]').value, request, {
                key: form.querySelector('[name="captcha_api_key"]').value,
                secret: form.querySelector('[name="captcha_api_secret"]').value,
            });
            status.textContent = 'Đã tạo task chạy lại bằng mật khẩu đã lưu. Không thu thêm phí.';
            await refresh();
        } catch (error) {
            status.textContent = error.message;
        }
    });
    let timer;
    let controller;
    let stream;
    let revision = 0;
    let stopped = false;
    const closeStream = () => {
        stream?.close();
        stream = null;
    };
    const connectStream = () => {
        if (stream || stopped || root.hidden) return;
        stream = new environment.EventSource(section.dataset.streamUrl);
        const source = stream;
        stream.addEventListener('orders', (event) => {
            if (stream !== source) return;
            try {
                const result = JSON.parse(event.data);
                if (!stopped && !root.hidden && typeof result.html === 'string') {
                    revision++;
                    updateHistory(result.html);
                    status.textContent = 'Trạng thái đang cập nhật realtime';
                }
            } catch {
                status.textContent = 'Chưa nhận được trạng thái mới. Đang kết nối lại…';
            }
        });
        for (const event of ['access-expired', 'maintenance']) {
            stream.addEventListener(event, () => {
                if (stream !== source) return;
                stopped = true;
                logModal.close();
                controller?.abort();
                closeStream();
                status.textContent = event === 'maintenance' ? 'Công cụ đang bảo trì.' : 'Phiên truy cập đã kết thúc. Vui lòng đăng nhập lại.';
            });
        }
        stream.onerror = () => {
            if (stream === source && !stopped && !root.hidden) {
                status.textContent = 'Đang kết nối lại cập nhật realtime…';
                void refresh();
            }
        };
    };
    const schedule = () => {
        environment.clearTimeout(timer);
        if (environment.EventSource && section.dataset.streamUrl) {
            connectStream();
            return;
        }
        if (!stopped && !root.hidden && content.querySelector('[data-password-active]')) timer = environment.setTimeout(refresh, 5000);
    };
    const refresh = async () => {
        if (controller || stopped || root.hidden) return;
        controller = new environment.AbortController();
        const requestedRevision = revision;
        try {
            const response = await request(section.dataset.url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if ([401, 403, 419].includes(response.status)) {
                stopped = true;
                logModal.close();
                closeStream();
                throw new Error('Phiên truy cập đã kết thúc. Vui lòng đăng nhập lại.');
            }
            if (!response.ok) throw new Error('Chưa cập nhật được lịch sử. Đang chờ thử lại.');
            const result = await response.json();
            if (!stopped && !root.hidden && revision === requestedRevision && typeof result.html === 'string') updateHistory(result.html);
            status.textContent = 'Đã cập nhật lịch sử';
        } catch (error) {
            if (error.name !== 'AbortError') status.textContent = error.message;
        } finally {
            controller = null;
            schedule();
        }
    };
    section.querySelector('[data-password-refresh]').addEventListener('click', refresh);
    root.addEventListener('visibilitychange', () => {
        if (root.hidden) {
            environment.clearTimeout(timer);
            closeStream();
            controller?.abort();
        } else schedule();
    });
    environment.addEventListener('pagehide', () => {
        stopped = true;
        logModal.close();
        closeStream();
        environment.clearTimeout(timer);
        controller?.abort();
    });
    environment.addEventListener('pageshow', () => {
        stopped = false;
        schedule();
    });
    renderGamePasswordHistory(content);
    schedule();
}

export function initializeGamePasswordCaptcha(root, request = fetch) {
    const panel = root.querySelector('[data-password-captcha]');
    if (!panel || panel.dataset.initialized) return;
    panel.dataset.initialized = 'true';
    const form = panel.closest('form');
    const key = panel.querySelector('[name="captcha_api_key"]');
    const secret = panel.querySelector('[name="captcha_api_secret"]');
    const save = panel.querySelector('[data-password-captcha-save]');
    const edit = panel.querySelector('[data-password-captcha-edit]');
    const cancel = panel.querySelector('[data-password-captcha-cancel]');
    const refresh = panel.querySelector('[data-password-captcha-refresh]');
    const profile = panel.querySelector('[data-password-captcha-profile]');
    const message = panel.querySelector('[data-password-captcha-message]');
    let saved = panel.dataset.saved === 'true';
    let busy = false;
    const editing = (enabled) => {
        for (const input of [key, secret]) {
            input.value = '';
            input.disabled = !enabled;
            input.required = enabled;
        }
        key.placeholder = enabled ? 'ak_xxx' : '***';
        secret.placeholder = enabled ? 'sk_xxx' : '***';
        save.hidden = !enabled;
        edit.hidden = enabled || !saved;
        cancel.hidden = !enabled || !saved;
        refresh.hidden = !saved;
    };
    const send = async (isRefresh) => {
        if (busy) return;
        if (!isRefresh && (!key.reportValidity() || !secret.reportValidity())) return;
        busy = true;
        [save, edit, cancel, refresh].forEach((button) => button.disabled = true);
        message.textContent = isRefresh ? 'Đang làm mới số dư…' : 'Đang kiểm tra và lưu khóa…';
        try {
            const response = await request(isRefresh ? panel.dataset.refreshUrl : panel.dataset.saveUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                body: JSON.stringify(isRefresh ? {} : { captcha_api_key: key.value, captcha_api_secret: secret.value }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || 'Chưa cập nhật được khóa giaicaptcha.vn. Vui lòng thử lại.');
            saved = true;
            panel.dataset.saved = 'true';
            profile.textContent = `${result.data.username} • ${Number(result.data.balance).toLocaleString('vi-VN', { maximumFractionDigits: 8 })} đ`;
            profile.hidden = false;
            if (!isRefresh) editing(false);
            message.textContent = isRefresh ? 'Đã cập nhật số dư mới nhất.' : 'Đã lưu khóa mã hóa. Các yêu cầu sau sẽ dùng khóa đã lưu.';
        } catch (error) {
            message.textContent = error.message;
        } finally {
            busy = false;
            [save, edit, cancel, refresh].forEach((button) => button.disabled = false);
        }
    };
    save.addEventListener('click', () => send(false));
    refresh.addEventListener('click', () => send(true));
    edit.addEventListener('click', () => { editing(true); key.focus(); });
    cancel.addEventListener('click', () => editing(false));
    form.addEventListener('submit', (event) => { if (busy) event.preventDefault(); });
}

export function initializeGamePasswordLogModal(root, section, content) {
    const dialog = root.querySelector('[data-password-log-modal]');
    if (!dialog) return { sync() {}, close() {} };
    const terminal = dialog.querySelector('[data-password-log-terminal]');
    const title = dialog.querySelector('[data-password-log-title]');
    let orderId;
    let previousOverflow = '';
    const close = () => {
        if (dialog.open) dialog.close();
    };
    const sync = (follow = false) => {
        if (!dialog.open) return;
        const log = [...content.querySelectorAll('[data-password-log]')].find((item) => item.dataset.passwordLog === orderId);
        if (!log) return;
        const atBottom = terminal.scrollHeight - terminal.clientHeight - terminal.scrollTop <= 4;
        const top = terminal.scrollTop;
        const text = log.value || 'Chưa có log chi tiết cho đơn này.';
        if (terminal.textContent !== text) terminal.textContent = text;
        terminal.scrollTop = follow || atBottom ? terminal.scrollHeight : top;
    };
    section.addEventListener('click', (event) => {
        const button = event.target.closest('[data-password-view-log]');
        if (!button || dialog.open) return;
        orderId = button.dataset.passwordViewLog;
        title.textContent = `Log đổi mật khẩu #${orderId}`;
        previousOverflow = root.documentElement.style.overflow;
        root.documentElement.style.overflow = 'hidden';
        dialog.showModal();
        sync(true);
    });
    dialog.querySelector('[data-password-log-close]').addEventListener('click', close);
    dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close();
    });
    dialog.addEventListener('close', () => {
        root.documentElement.style.overflow = previousOverflow;
        terminal.textContent = '';
        const button = [...content.querySelectorAll('[data-password-view-log]')].find((item) => item.dataset.passwordViewLog === orderId);
        button?.focus({ preventScroll: true });
        orderId = null;
    });
    return { sync, close };
}

export function renderGamePasswordHistory(content, html) {
    const positions = new Map();
    const tableScroll = content.firstElementChild?.scrollLeft || 0;
    if (typeof html === 'string') {
        content.querySelectorAll('[data-password-log]').forEach((log) => {
            positions.set(log.dataset.passwordLog, {
                top: log.scrollTop,
                follow: log.scrollHeight - log.clientHeight - log.scrollTop <= 4,
            });
        });
        content.innerHTML = html;
        if (content.firstElementChild) content.firstElementChild.scrollLeft = tableScroll;
    }
    content.querySelectorAll('[data-password-log]').forEach((log) => {
        const position = positions.get(log.dataset.passwordLog);
        log.scrollTop = !position || position.follow ? log.scrollHeight : position.top;
    });
}

export async function retrySavedGamePassword(button, csrfToken, request = fetch, captchaCredentials = {}) {
    if (button.disabled) return false;
    button.disabled = true;
    let queued = false;
    try {
        const response = await request(button.dataset.retryUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
                attempt_version: Number(button.dataset.attemptVersion),
                ...(captchaCredentials.key || captchaCredentials.secret
                    ? { captcha_api_key: captchaCredentials.key, captcha_api_secret: captchaCredentials.secret }
                    : {}),
            }),
        });
        const result = await response.json();
        if (!response.ok)
            throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Chưa thể chạy lại đơn. Vui lòng thử lại.');
        queued = true;
        return true;
    } finally {
        if (!queued) button.disabled = false;
    }
}
