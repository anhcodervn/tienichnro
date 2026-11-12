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
    let previewUrl;
    if (solveForm) {
        solveForm.elements.image.addEventListener('change', () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            const preview = root.querySelector('[data-captcha-preview]');
            const file = solveForm.elements.image.files[0];
            preview.hidden = !file;
            if (file) {
                previewUrl = URL.createObjectURL(file);
                preview.src = previewUrl;
            }
        });
        solveForm.addEventListener('submit', (event) => {
            event.preventDefault();
            void busy(solveForm, async () => {
                const file = solveForm.elements.image.files[0];
                if (!file || file.size > 2 * 1024 * 1024) throw new Error('Chọn ảnh tối đa 2MB.');
                const image = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = () => reject(new Error('Không đọc được ảnh.'));
                    reader.readAsDataURL(file);
                });
                const data = await request(root.dataset.solveUrl, 'POST', { image });
                root.querySelector('[data-captcha-result]').textContent = data.status
                    ? 'Kết quả: ' + data.code + ' · ' + data.time + ' giây'
                    : data.message || 'Chưa có kết quả giải captcha.';
                showMessage(data.status ? 'Đã trừ 1 token. Mỗi lần bấm giải sẽ tạo lượt mới.' : 'Chưa trừ token.');
                await loadHistory();
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
    let solvesPage = 1;
    let transactionsPage = 1;
    const renderRows = (selector, rows, format) => {
        const container = root.querySelector(selector);
        container.replaceChildren();
        if (!rows.length) container.textContent = 'Chưa có dữ liệu.';
        rows.forEach((row) => {
            const item = document.createElement('p');
            item.className = 'rounded-lg border border-slate-200 p-3';
            item.textContent = format(row);
            container.appendChild(item);
        });
    };
    async function loadHistory() {
        if (!root.querySelector('[data-captcha-history]')) return;
        try {
            const data = await request(root.dataset.historyUrl + '?solves_page=' + solvesPage + '&transactions_page=' + transactionsPage);
            balance(data);
            renderRows(
                '[data-captcha-history]',
                data.solves.data,
                (row) => '#' + row.id + ' · ' + row.status + ' · ' + (row.result || '—') + ' · ' + new Date(row.created_at).toLocaleString('vi-VN'),
            );
            renderRows(
                '[data-captcha-transactions]',
                data.transactions.data,
                (row) => (row.direction === 'credit' ? '+' : '-') + row.amount + ' token · ' + row.description + ' · Số dư: ' + row.balance_after,
            );
            for (const [name, pagination] of [
                ['solves', data.solves],
                ['transactions', data.transactions],
            ]) {
                root.querySelector('[data-captcha-' + name + '-prev]').disabled = pagination.current_page <= 1;
                root.querySelector('[data-captcha-' + name + '-next]').disabled = pagination.current_page >= pagination.last_page;
            }
        } catch (error) {
            showMessage(error.message);
        }
    }
    root.querySelector('[data-captcha-history-load]')?.addEventListener('click', () => void loadHistory());
    for (const name of ['solves', 'transactions']) {
        for (const [direction, offset] of [
            ['prev', -1],
            ['next', 1],
        ]) {
            root.querySelector('[data-captcha-' + name + '-' + direction + ']')?.addEventListener('click', () => {
                if (name === 'solves') solvesPage += offset;
                else transactionsPage += offset;
                void loadHistory();
            });
        }
    }
    void loadHistory();
}
