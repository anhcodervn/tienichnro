export function initializeClientRecharge(root = document, environment = window, request = fetch) {
    initializeRechargeForms(root, environment);
    root.querySelectorAll('[data-wallet-recharge]').forEach((section) => {
        if (section.dataset.initialized === 'true') return;
        section.dataset.initialized = 'true';
        let timer;
        let busy = false;
        let stopped = !['pending', 'processing', 'creating'].includes(section.dataset.status);
        const message = section.querySelector('[data-recharge-live-status]');
        let countdownTimer;
        const countdown = section.querySelector('[data-recharge-countdown]');
        const tick = () => {
            if (!countdown || root.hidden) return;
            environment.clearTimeout(countdownTimer);
            const expires = Date.parse(section.dataset.expiresAt);
            if (!Number.isFinite(expires)) return;
            const seconds = Math.max(0, Math.ceil((expires - Date.now()) / 1000));
            countdown.textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
            if (seconds === 0) {
                section.querySelector('[data-recharge-qr]')?.setAttribute('hidden', '');
                section.querySelectorAll('[data-recharge-copy]').forEach((button) => {
                    button.disabled = true;
                });
                section.querySelector('[data-recharge-expired]')?.classList.remove('hidden');
            } else countdownTimer = environment.setTimeout(tick, 1000);
        };
        section.querySelector('[data-recharge-qr-image]')?.addEventListener('error', () => {
            section.querySelector('[data-recharge-qr-image]').hidden = true;
            section.querySelector('[data-recharge-qr-error]')?.classList.remove('hidden');
        });
        section.querySelectorAll('[data-recharge-copy]').forEach((button) =>
            button.addEventListener('click', async () => {
                const status = section.querySelector('[data-recharge-copy-status]');
                try {
                    await environment.navigator.clipboard.writeText(button.dataset.rechargeCopy);
                    status.textContent = 'Đã sao chép.';
                } catch {
                    status.textContent = 'Không sao chép được. Vui lòng chọn và sao chép thông tin thủ công.';
                }
            }),
        );
        section.querySelector('[data-recharge-content]')?.addEventListener('click', (event) => event.target.select());
        const close = () => {
            environment.clearTimeout(timer);
        };
        const check = async () => {
            if (busy || stopped || root.hidden) return;
            close();
            busy = true;
            try {
                const response = await request(section.dataset.statusUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if ([401, 403, 404].includes(response.status)) {
                    stopped = true;
                    return;
                }
                if (!response.ok) throw new Error();
                const result = await response.json();
                if (stopped) return;
                if (result.data.status !== section.dataset.status) {
                    stopped = true;
                    environment.location.reload();
                    return;
                }
                message.textContent = 'Đang chờ xác nhận giao dịch từ hệ thống.';
            } catch {
                message.textContent = 'Chưa cập nhật được trạng thái. Hệ thống sẽ thử lại.';
            } finally {
                busy = false;
                if (!stopped && !root.hidden) timer = environment.setTimeout(check, 5000);
            }
        };
        section.querySelector('[data-recharge-refresh]').addEventListener('click', () => {
            if (stopped) environment.location.reload();
            else void check();
        });
        root.addEventListener('visibilitychange', () => {
            if (root.hidden) {
                close();
                environment.clearTimeout(countdownTimer);
            } else {
                tick();
                void check();
            }
        });
        environment.addEventListener('pagehide', () => {
            stopped = true;
            close();
            environment.clearTimeout(countdownTimer);
        });
        environment.addEventListener('pageshow', () => {
            stopped = !['pending', 'processing', 'creating'].includes(section.dataset.status);
            tick();
            void check();
        });
        if (!stopped) timer = environment.setTimeout(check, 5000);
        tick();
    });
}

export function initializeRechargeForms(root = document, environment = window) {
    root.querySelectorAll('[data-recharge-form]').forEach((form) => {
        if (form.dataset.initialized === 'true') return;
        form.dataset.initialized = 'true';
        const input = form.querySelector('[data-recharge-amount]');
        const summary = form.querySelector('[data-recharge-summary]');
        const submit = form.querySelector('[data-recharge-submit]');
        const label = form.querySelector('[data-recharge-submit-label]');
        const presets = form.querySelectorAll('[data-recharge-preset]');
        let submitting = false;
        const numericAmount = () => Number(String(input.value).replaceAll('.', ''));
        const update = () => {
            const amount = numericAmount();
            const valid =
                /^(?:[0-9]+|[0-9]{1,3}(?:\.[0-9]{3})+)$/.test(input.value) && Number.isInteger(amount) && amount >= 10000 && amount <= 50000000;
            input.setCustomValidity?.(valid ? '' : 'Nhập số tiền từ 10.000đ đến 50.000.000đ.');
            summary.textContent = valid ? new Intl.NumberFormat('vi-VN').format(amount) + 'đ' : '—';
            presets.forEach((button) => button.setAttribute('aria-pressed', String(Number(button.dataset.rechargePreset) === amount)));
        };
        presets.forEach((button) =>
            button.addEventListener('click', () => {
                input.value = new Intl.NumberFormat('vi-VN').format(Number(button.dataset.rechargePreset));
                update();
            }),
        );
        input.addEventListener('input', update);
        input.addEventListener('blur', () => {
            if (input.checkValidity?.()) input.value = new Intl.NumberFormat('vi-VN').format(numericAmount());
        });
        form.addEventListener('submit', (event) => {
            if (submitting || !form.checkValidity()) {
                event.preventDefault();
                return;
            }
            submitting = true;
            submit.disabled = true;
            label.textContent = 'Đang tạo lệnh…';
        });
        environment.addEventListener('pageshow', () => {
            submitting = false;
            submit.disabled = false;
            label.textContent = 'Tạo yêu cầu nạp';
        });
        update();
    });
}
