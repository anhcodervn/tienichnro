export function initializeCaptchaHistoryTabs(root) {
    const tabs = Array.from(root.querySelectorAll('[data-captcha-history-tab]'));
    const panels = Array.from(root.querySelectorAll('[data-captcha-history-panel]'));
    const activate = (selected) => {
        tabs.forEach((tab) => {
            const active = tab === selected;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
            tab.classList.toggle('client-button', active);
            tab.classList.toggle('client-button-secondary', !active);
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.captchaHistoryPanel !== selected.dataset.captchaHistoryTab;
        });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab));
        tab.addEventListener('keydown', (event) => {
            const next = {
                ArrowLeft: (index - 1 + tabs.length) % tabs.length,
                ArrowRight: (index + 1) % tabs.length,
                Home: 0,
                End: tabs.length - 1,
            }[event.key];
            if (next === undefined) return;
            event.preventDefault();
            activate(tabs[next]);
            tabs[next].focus();
        });
    });
}

export function initializeCaptchaTool() {
    const root = document.querySelector('[data-captcha-tool]');
    if (!root) return;
    initializeCaptchaHistoryTabs(root);
    const rates = JSON.parse(root.dataset.rates);
    const message = root.querySelector('[data-captcha-message]');
    const showMessage = (text) => {
        message.textContent = text;
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const request = async (url, method = 'GET', body) => {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            ...(body ? { body: JSON.stringify(body) } : {}),
        });
        const data = await response.json();
        if (!response.ok)
            throw new Error(
                Object.values(data.errors || {})
                    .flat()
                    .join(' ') ||
                    data.message ||
                    'Yêu cầu thất bại.',
            );
        return data;
    };
    const balance = (data) => {
        if (data.balance !== undefined) root.querySelector('[data-captcha-balance]').textContent = Number(data.balance).toLocaleString('vi-VN');
    };
    const busy = async (form, callback) => {
        const button = form.querySelector('[type="submit"]');
        if (button.disabled) return;
        button.disabled = true;
        try {
            await callback();
        } catch (error) {
            showMessage(error.message);
        } finally {
            button.disabled = false;
        }
    };
    const modal = root.querySelector('[data-captcha-modal]');
    const purchase = root.querySelector('[data-captcha-purchase]');
    let purchaseAttempt = null;
    const priceFor = (tokens) => rates.reduce((price, rate) => (tokens >= rate.min_tokens ? Number(rate.unit_price) : price), 5);
    if (purchase) {
        const tokensInput = purchase.elements.tokens;
        const updatePrice = () => {
            const tokens = Number(tokensInput.value);
            const unitPrice = priceFor(tokens);
            purchase.querySelector('[data-captcha-price]').textContent =
                unitPrice.toLocaleString('vi-VN') + 'đ/token · Tổng: ' + (tokens * unitPrice).toLocaleString('vi-VN') + 'đ';
        };
        tokensInput.addEventListener('input', () => {
            purchaseAttempt = null;
            updatePrice();
        });
        root.querySelector('[data-captcha-buy]').addEventListener('click', () => {
            updatePrice();
            modal.showModal();
        });
        root.querySelector('[data-captcha-close]').addEventListener('click', () => modal.close());
        purchase.addEventListener('submit', (event) => {
            event.preventDefault();
            void busy(purchase, async () => {
                const tokens = Number(tokensInput.value);
                purchaseAttempt ||= { tokens, unit_price: priceFor(tokens), request_id: crypto.randomUUID() };
                const data = await request(root.dataset.purchaseUrl, 'POST', purchaseAttempt);
                balance(data);
                purchaseAttempt = null;
                modal.close();
                showMessage('Đã mua token. Tải lại trang để cập nhật số dư ví chính.');
                await loadHistory();
            });
        });
    }
    const solveForm = root.querySelector('[data-captcha-solve]');
    if (solveForm) {
        initializeCaptchaUpload(solveForm, showMessage);
        solveForm.addEventListener('submit', (event) => {
            event.preventDefault();
            void busy(solveForm, async () => {
                const file = solveForm.elements.image.files[0];
                if (!file || file.size > 2 * 1024 * 1024) throw new Error('Chọn ảnh tối đa 2MB.');
                const token = new FormData(solveForm).get('cf-turnstile-response');
                if (!token) throw new Error('Vui lòng hoàn tất xác minh Cloudflare trước khi giải miễn phí.');
                root.querySelector('[data-captcha-result]').textContent = '';
                const image = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = () => reject(new Error('Không đọc được ảnh.'));
                    reader.readAsDataURL(file);
                });
                try {
                    const data = await request(root.dataset.solveUrl, 'POST', { image, 'cf-turnstile-response': token });
                    root.querySelector('[data-captcha-result]').textContent = data.status
                        ? 'Kết quả: ' + data.code + ' · ' + data.time + ' giây'
                        : data.message || 'Chưa có kết quả giải captcha.';
                    showMessage('Lượt thử miễn phí, không trừ token_captcha.');
                    await loadHistory();
                } finally {
                    window.turnstile?.reset();
                }
            });
        });
    }
    root.querySelector('[data-captcha-key-create]')?.addEventListener('click', async (event) => {
        if (!window.confirm('Tạo token mới sẽ vô hiệu hóa token cũ. Tiếp tục?')) return;
        event.target.disabled = true;
        try {
            const data = await request(root.dataset.keyUrl, 'POST');
            root.querySelector('[data-captcha-key-value]').value = data.token;
            root.querySelector('[data-captcha-key-status]').textContent = 'Đã tạo token mới. Hãy lưu lại ngay.';
        } catch (error) {
            showMessage(error.message);
        } finally {
            event.target.disabled = false;
        }
    });
    root.querySelector('[data-captcha-key-revoke]')?.addEventListener('click', async () => {
        try {
            await request(root.dataset.keyUrl, 'DELETE');
            root.querySelector('[data-captcha-key-value]').value = '';
            root.querySelector('[data-captcha-key-status]').textContent = 'Đã thu hồi token.';
        } catch (error) {
            showMessage(error.message);
        }
    });
    const loadHistory = initializeCaptchaHistory(root, request, balance, showMessage);
    void loadHistory();
}

export function initializeCaptchaUpload(form, showMessage) {
    const input = form.elements.image;
    const dropzone = form.querySelector('[data-captcha-dropzone]');
    const preview = form.querySelector('[data-captcha-preview]');
    const name = form.querySelector('[data-captcha-file-name]');
    let previewUrl;
    let dragDepth = 0;
    const highlight = (active) => {
        dropzone.classList.toggle('!border-emerald-500', active);
        dropzone.classList.toggle('!bg-emerald-50', active);
    };
    input.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
        preview.hidden = true;
        preview.removeAttribute('src');
        name.textContent = 'Kéo thả ảnh vào đây hoặc bấm để chọn ảnh';
        const file = input.files[0];
        if (!file) return;
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            input.value = '';
            showMessage('Chọn ảnh PNG, JPEG hoặc WebP, tối đa 2 MB.');
            return;
        }
        name.textContent = file.name;
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.hidden = false;
        showMessage('');
    });
    for (const eventName of ['dragenter', 'dragover', 'dragleave', 'drop']) {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            if (eventName === 'dragenter') dragDepth++;
            if (eventName === 'dragleave') dragDepth = Math.max(0, dragDepth - 1);
            if (eventName === 'drop') {
                dragDepth = 0;
                if (event.dataTransfer.files.length !== 1) {
                    showMessage('Vui lòng chọn một ảnh mỗi lượt.');
                } else {
                    input.files = event.dataTransfer.files;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
            highlight(dragDepth > 0 || eventName === 'dragover');
        });
    }
}

export function initializeCaptchaHistory(root, request, balance, showMessage) {
    const pages = { solves: 1, transactions: 1 };
    const filters = { solves: {}, transactions: {} };
    const pagination = {};
    let revision = 0;
    const formatNumber = (value) => Number(value).toLocaleString('vi-VN');
    const date = (value) => new Date(value).toLocaleString('vi-VN');
    const statusLabels = { success: 'Thành công', failed: 'Thất bại', processing: 'Đang xử lý' };
    const statusClasses = {
        success: 'bg-emerald-50 text-emerald-700 ring-emerald-600/10',
        failed: 'bg-rose-50 text-rose-700 ring-rose-600/10',
        processing: 'bg-amber-50 text-amber-700 ring-amber-600/10',
    };
    const cell = (row, value, classes = '') => {
        const td = document.createElement('td');
        td.className = 'px-3 py-3 text-slate-600 ' + classes;
        td.textContent = value;
        row.appendChild(td);
        return td;
    };
    const render = (name, data, emptyMessage = 'Không có dữ liệu phù hợp.') => {
        const body = root.querySelector(name === 'solves' ? '[data-captcha-history]' : '[data-captcha-transactions]');
        body.replaceChildren();
        if (!data.length) {
            const row = document.createElement('tr');
            const td = cell(row, emptyMessage, 'text-center !py-10 text-slate-400');
            td.colSpan = name === 'solves' ? 6 : 5;
            body.appendChild(row);
        }
        data.forEach((item) => {
            const row = document.createElement('tr');
            row.className = 'transition-colors hover:bg-slate-50/70';
            cell(row, '#' + item.id, 'tabular-nums text-slate-400');
            if (name === 'solves') {
                cell(row, item.result || '—', 'font-mono font-semibold !text-slate-800');
                const badge = document.createElement('span');
                badge.className =
                    'inline-flex rounded-full px-2 py-1 text-[11px] font-medium ring-1 ring-inset ' +
                    (statusClasses[item.status] || 'bg-slate-100 text-slate-600');
                badge.textContent = statusLabels[item.status] || item.status;
                cell(row, '').appendChild(badge);
                cell(row, item.source === 'api' ? 'API' : 'Demo');
                cell(row, item.duration_ms == null ? '—' : formatNumber(item.duration_ms / 1000) + ' s', 'text-right tabular-nums');
            } else {
                cell(row, item.description, 'min-w-48 max-w-md break-words');
                cell(
                    row,
                    (item.direction === 'credit' ? '+' : '−') + formatNumber(item.amount),
                    'text-right whitespace-nowrap font-semibold tabular-nums ' +
                        (item.direction === 'credit' ? '!text-emerald-700' : '!text-rose-600'),
                );
                cell(row, formatNumber(item.balance_after), 'text-right tabular-nums');
            }
            cell(row, date(item.created_at), 'whitespace-nowrap tabular-nums text-slate-400');
            body.appendChild(row);
        });
    };
    const setBusy = (busy) => {
        root.querySelectorAll('[data-captcha-history-panel]').forEach((panel) => {
            panel.setAttribute('aria-busy', String(busy));
            panel.querySelectorAll('button').forEach((button) => {
                button.disabled = busy;
            });
        });
        const refresh = root.querySelector('[data-captcha-history-load]');
        if (refresh) refresh.disabled = busy;
        if (!busy) {
            for (const name of ['solves', 'transactions']) {
                const data = pagination[name];
                root.querySelector('[data-captcha-' + name + '-prev]').disabled = !data || data.current_page <= 1;
                root.querySelector('[data-captcha-' + name + '-next]').disabled = !data || data.current_page >= data.last_page;
            }
        }
    };
    async function loadHistory() {
        if (!root.querySelector('[data-captcha-history]')) return;
        const current = ++revision;
        setBusy(true);
        const params = new URLSearchParams({
            ...filters.solves,
            ...filters.transactions,
            solves_page: pages.solves,
            transactions_page: pages.transactions,
        });
        try {
            const data = await request(root.dataset.historyUrl + '?' + params);
            if (current !== revision) return;
            balance(data);
            for (const name of ['solves', 'transactions']) {
                pagination[name] = data[name];
                pages[name] = data[name].current_page;
                render(name, data[name].data);
                root.querySelector('[data-captcha-' + name + '-summary]').textContent =
                    (data[name].from || 0) + '–' + (data[name].to || 0) + ' / ' + formatNumber(data[name].total) + ' bản ghi';
                root.querySelector('[data-captcha-' + name + '-page]').textContent =
                    'Trang ' + data[name].current_page + ' / ' + data[name].last_page;
            }
        } catch (error) {
            if (current !== revision) return;
            showMessage(error.message);
            for (const name of ['solves', 'transactions']) {
                pagination[name] = null;
                render(name, [], 'Không tải được lịch sử. Bấm Làm mới để thử lại.');
                root.querySelector('[data-captcha-' + name + '-summary]').textContent = '';
                root.querySelector('[data-captcha-' + name + '-page]').textContent = '';
            }
        } finally {
            if (current === revision) setBusy(false);
        }
    }
    root.querySelector('[data-captcha-history-load]')?.addEventListener('click', () => void loadHistory());
    for (const name of ['solves', 'transactions']) {
        const form = root.querySelector('[data-captcha-filters="' + name + '"]');
        form?.addEventListener('submit', (event) => {
            event.preventDefault();
            filters[name] = Object.fromEntries([...new FormData(form)].filter(([, value]) => value !== ''));
            pages[name] = 1;
            void loadHistory();
        });
        form?.addEventListener('reset', () => {
            filters[name] = {};
            pages[name] = 1;
            void loadHistory();
        });
        for (const [direction, offset] of [
            ['prev', -1],
            ['next', 1],
        ]) {
            root.querySelector('[data-captcha-' + name + '-' + direction + ']')?.addEventListener('click', () => {
                const next = pages[name] + offset;
                if (!pagination[name] || next < 1 || next > pagination[name].last_page) return;
                pages[name] = next;
                void loadHistory();
            });
        }
    }
    return loadHistory;
}
