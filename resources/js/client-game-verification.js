import Swal from 'sweetalert2';

export function verificationSmsUrl(syntax, userAgent = '') {
    if (!/^GO NRO-XT [^\s|]+ [A-Za-z0-9]{8}$/.test(syntax)) throw new Error('Cú pháp SMS không hợp lệ.');
    return `sms:6020${/iPhone|iPad|iPod/i.test(userAgent) ? '&' : '?'}body=${encodeURIComponent(syntax)}`;
}

export function reconcileVerificationSelection(selected, checkboxes) {
    const available = new Set(checkboxes.filter((input) => !input.disabled).map((input) => input.dataset.verificationSelect));
    for (const id of selected) if (!available.has(id)) selected.delete(id);
    for (const input of checkboxes) input.checked = selected.has(input.dataset.verificationSelect);
}

export async function exportVerificationAccounts(url, ids, format, newPassword, csrfToken, request = fetch) {
    const response = await request(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ ids: ids.map(Number), format, ...(format === 'password' ? { new_password: newPassword } : {}) }),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || 'Chưa lấy được dữ liệu tài khoản đã chọn.');
    return result.data;
}

export function initializeVerificationSelection(root, section, content, environment, request) {
    const selected = new Set();
    const modal = root.querySelector('[data-verification-sms-modal]');
    const list = modal.querySelector('[data-verification-sms-list]');
    const message = section.querySelector('[data-verification-history-status]');
    const counter = section.querySelector('[data-verification-selected]');
    let busy = false;
    let previousOverflow = '';
    let opener;
    const sync = () => {
        const inputs = [...content.querySelectorAll('[data-verification-select]')];
        reconcileVerificationSelection(selected, inputs);
        counter.textContent = `${selected.size} tài khoản đã chọn`;
        const all = content.querySelector('[data-verification-select-all]');
        if (all) {
            const available = inputs.filter((input) => !input.disabled);
            all.checked = available.length > 0 && available.every((input) => input.checked);
            all.indeterminate = selected.size > 0 && !all.checked;
            all.disabled = available.length === 0;
        }
        section.querySelectorAll('[data-verification-actions] button').forEach((button) => (button.disabled = busy || selected.size === 0));
    };
    const close = () => {
        if (modal.open) modal.close();
    };
    modal.querySelector('[data-verification-sms-close]').addEventListener('click', close);
    modal.addEventListener('close', () => {
        root.documentElement.style.overflow = previousOverflow;
        list.replaceChildren();
        if (opener?.isConnected) opener.focus({ preventScroll: true });
    });
    modal.addEventListener('click', (event) => {
        if (event.target !== modal) return;
        const bounds = modal.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close();
    });
    section.addEventListener('change', (event) => {
        if (event.target.matches('[data-verification-select-all]')) {
            content.querySelectorAll('[data-verification-select]:not(:disabled)').forEach((input) => {
                if (event.target.checked) selected.add(input.dataset.verificationSelect);
                else selected.delete(input.dataset.verificationSelect);
            });
        } else if (event.target.matches('[data-verification-select]')) {
            const id = event.target.dataset.verificationSelect;
            if (event.target.checked) selected.add(id);
            else selected.delete(id);
        }
        sync();
    });
    section.addEventListener('click', async (event) => {
        const button = event.target.closest(
            '[data-verification-export], [data-verification-sms], [data-verification-copy-one], [data-verification-sms-one], [data-verification-password-one]',
        );
        if (!button || button.disabled || busy) return;
        const one = button.dataset.verificationCopyOne || button.dataset.verificationSmsOne || button.dataset.verificationPasswordOne;
        const ids = one ? [one] : [...selected];
        if (!ids.length) return;
        const format = button.dataset.verificationExport === 'password' || button.dataset.verificationPasswordOne ? 'password' : 'sms';
        busy = true;
        sync();
        try {
            let newPassword;
            if (format === 'password') {
                const answer = await Swal.fire({
                    title: 'Mật khẩu mới cho danh sách',
                    input: 'password',
                    inputPlaceholder: 'Tối thiểu 6 ký tự',
                    inputAttributes: { autocomplete: 'new-password', maxlength: '200' },
                    text: 'Copy dạng tk|mkc|sv|mkm để dán vào công cụ đổi mật khẩu. Thao tác này chưa tạo task hay trừ phí.',
                    showCancelButton: true,
                    confirmButtonText: 'Copy danh sách',
                    cancelButtonText: 'Hủy',
                    inputValidator: (value) =>
                        value.length >= 6 && value.length <= 200 && !/[\r\n|]/.test(value)
                            ? undefined
                            : 'Nhập 6–200 ký tự, không có | hoặc xuống dòng.',
                });
                if (!answer.isConfirmed) return;
                newPassword = answer.value;
            }
            const csrf = root.querySelector('[data-verification-form] [name="_token"]').value;
            const items = await exportVerificationAccounts(section.dataset.exportUrl, ids, format, newPassword, csrf, request);
            if (button.hasAttribute('data-verification-sms') || button.dataset.verificationSmsOne) {
                list.replaceChildren();
                for (const item of items) {
                    const row = root.createElement('div');
                    row.className = 'grid gap-3 rounded-[10px] border border-slate-200 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center';
                    const text = root.createElement('p');
                    text.className = 'break-words font-mono text-xs leading-6 text-slate-700';
                    text.textContent = item.syntax;
                    const link = root.createElement('a');
                    link.className = 'client-button !min-h-9 !px-3 !text-xs';
                    link.textContent = 'Mở SMS → 6020';
                    link.href = verificationSmsUrl(item.syntax, environment.navigator.userAgent);
                    row.append(text, link);
                    list.appendChild(row);
                }
                opener = button;
                previousOverflow = root.documentElement.style.overflow;
                root.documentElement.style.overflow = 'hidden';
                modal.showModal();
            } else {
                const text = items.map((item) => (format === 'password' ? item.line : item.syntax)).join('\n');
                try {
                    await environment.navigator.clipboard.writeText(text);
                    message.textContent = `Đã copy ${items.length} tài khoản.`;
                } catch {
                    await Swal.fire({
                        title: 'Sao chép danh sách',
                        input: 'textarea',
                        inputValue: text,
                        inputAttributes: { readonly: 'true', rows: '8' },
                        confirmButtonText: 'Đóng',
                    });
                }
            }
        } catch (error) {
            message.textContent = error.message;
        } finally {
            busy = false;
            sync();
        }
    });
    environment.addEventListener('pagehide', close);
    return { sync, close };
}

export function initializeGameVerification(root = document, environment = window, request = fetch) {
    const form = root.querySelector('[data-verification-form]');
    if (form && !form.dataset.initialized) {
        form.dataset.initialized = 'true';
        const toggle = form.querySelector('[data-verification-bulk-toggle]');
        const single = form.querySelector('[data-verification-single]');
        const bulk = form.querySelector('[data-verification-bulk]');
        const text = bulk.querySelector('textarea');
        const button = form.querySelector('button[type="submit"]');
        const label = button.querySelector('[data-verification-submit-label]');
        let submitting = false;
        form.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-verification-visibility]');
            if (!toggle) return;
            const input = form.querySelector(`#${toggle.dataset.verificationVisibility}`);
            if (!input || input.disabled) return;
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            const label = `${visible ? 'Ẩn' : 'Hiện'} ${toggle.dataset.verificationLabel}`;
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
            label.textContent = submitting ? 'Đang gửi yêu cầu…' : `Lấy cú pháp · ${count} tài khoản`;
            if (form.dataset.price === '') {
                form.querySelector('[data-verification-total]').textContent = 'Ch\u01b0a c\u1ea5u h\u00ecnh gi\u00e1';
                return;
            }
            form.querySelector('[data-verification-total]').textContent = `${count} tài khoản · Tổng phí ${amount.toLocaleString('vi-VN')} đ`;
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
    const section = root.querySelector('[data-verification-history]');
    if (!section || section.dataset.initialized) return;
    section.dataset.initialized = 'true';
    const content = section.querySelector('[data-verification-history-content]');
    const status = section.querySelector('[data-verification-history-status]');
    const logModal = initializeGameVerificationLogModal(root, section, content);
    const selection = initializeVerificationSelection(root, section, content, environment, request);
    const updateHistory = (html) => {
        renderGameVerificationHistory(content, html);
        logModal.sync();
        selection.sync();
    };
    section.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-verification-retry]');
        if (!button || button.disabled || !form) return;
        try {
            status.textContent = 'Đang đưa đơn vào hàng chờ…';
            await retrySavedGameVerification(button, form.querySelector('input[name="_token"]').value, request, {
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
                selection.close();
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
        if (!stopped && !root.hidden && content.querySelector('[data-verification-active]')) timer = environment.setTimeout(refresh, 5000);
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
                selection.close();
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
    section.querySelector('[data-verification-refresh]').addEventListener('click', refresh);
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
        selection.close();
        closeStream();
        environment.clearTimeout(timer);
        controller?.abort();
    });
    environment.addEventListener('pageshow', () => {
        stopped = false;
        schedule();
    });
    renderGameVerificationHistory(content);
    selection.sync();
    schedule();
}

export function initializeGameVerificationLogModal(root, section, content) {
    const dialog = root.querySelector('[data-verification-log-modal]');
    if (!dialog) return { sync() {}, close() {} };
    const terminal = dialog.querySelector('[data-verification-log-terminal]');
    const title = dialog.querySelector('[data-verification-log-title]');
    let orderId;
    let previousOverflow = '';
    const close = () => {
        if (dialog.open) dialog.close();
    };
    const sync = (follow = false) => {
        if (!dialog.open) return;
        const log = [...content.querySelectorAll('[data-verification-log]')].find((item) => item.dataset.verificationLog === orderId);
        if (!log) return;
        const atBottom = terminal.scrollHeight - terminal.clientHeight - terminal.scrollTop <= 4;
        const top = terminal.scrollTop;
        const text = log.value || 'Chưa có log chi tiết cho đơn này.';
        if (terminal.textContent !== text) terminal.textContent = text;
        terminal.scrollTop = follow || atBottom ? terminal.scrollHeight : top;
    };
    section.addEventListener('click', (event) => {
        const button = event.target.closest('[data-verification-view-log]');
        if (!button || dialog.open) return;
        orderId = button.dataset.verificationViewLog;
        title.textContent = `Log lấy cú pháp #${orderId}`;
        previousOverflow = root.documentElement.style.overflow;
        root.documentElement.style.overflow = 'hidden';
        dialog.showModal();
        sync(true);
    });
    dialog.querySelector('[data-verification-log-close]').addEventListener('click', close);
    dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close();
    });
    dialog.addEventListener('close', () => {
        root.documentElement.style.overflow = previousOverflow;
        terminal.textContent = '';
        const button = [...content.querySelectorAll('[data-verification-view-log]')].find((item) => item.dataset.verificationViewLog === orderId);
        button?.focus({ preventScroll: true });
        orderId = null;
    });
    return { sync, close };
}

export function renderGameVerificationHistory(content, html) {
    const positions = new Map();
    const tableScroll = content.firstElementChild?.scrollLeft || 0;
    if (typeof html === 'string') {
        content.querySelectorAll('[data-verification-log]').forEach((log) => {
            positions.set(log.dataset.verificationLog, {
                top: log.scrollTop,
                follow: log.scrollHeight - log.clientHeight - log.scrollTop <= 4,
            });
        });
        content.innerHTML = html;
        if (content.firstElementChild) content.firstElementChild.scrollLeft = tableScroll;
    }
    content.querySelectorAll('[data-verification-log]').forEach((log) => {
        const position = positions.get(log.dataset.verificationLog);
        log.scrollTop = !position || position.follow ? log.scrollHeight : position.top;
    });
}

export async function retrySavedGameVerification(button, csrfToken, request = fetch, captchaCredentials = {}) {
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
