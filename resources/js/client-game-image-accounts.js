import Swal from 'sweetalert2';

export function initializeGameImageAccounts(root = document, alerts = Swal, request = fetch, reload = () => window.location.reload()) {
    initializeGameImageAccountDetails(root, request, reload);
    initializeGameImageOrderControls(root, alerts, request, reload);
    root.querySelectorAll('[data-game-image-username-toggle]').forEach((button) => {
        const cell = button.closest('[data-game-image-username]');
        const value = cell?.querySelector('[data-game-image-username-value]');
        if (!cell || !value || button.dataset.initialized === 'true') return;
        button.dataset.initialized = 'true';
        button.addEventListener('click', () => {
            const show = button.getAttribute('aria-pressed') !== 'true';
            value.textContent = show ? cell.dataset.username : cell.dataset.maskedUsername;
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Ẩn tên tài khoản' : 'Hiện tên tài khoản');
            button.querySelector('i')?.classList.toggle('bx-eye', !show);
            button.querySelector('i')?.classList.toggle('bx-eye-slash', show);
        });
    });
    root.querySelectorAll('[data-game-image-recapture]').forEach((button) => {
        if (button.dataset.recaptureInitialized === 'true') return;
        button.dataset.recaptureInitialized = 'true';
        let pending = false;
        button.addEventListener('click', async () => {
            if (pending || button.disabled) return;
            pending = true;
            button.disabled = true;
            let submitted = false;
            try {
                const price = Number(button.dataset.price);
                const result = await alerts.fire({
                    title: 'Chụp lại ảnh tài khoản?',
                    text: `Bạn cần tải lại ảnh mới nhất của account để ghép, phí ${price.toLocaleString('vi-VN')}đ mỗi lần chụp`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Chụp lại',
                    cancelButtonText: 'Hủy',
                    confirmButtonColor: '#059669',
                    focusCancel: true,
                    reverseButtons: true,
                });
                if (!result.isConfirmed) return;
                const response = await request(button.dataset.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': root.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ price, capture_version: Number(button.dataset.version) }),
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'Chưa thể yêu cầu chụp lại.');
                }
                submitted = true;
                reload();
            } catch (error) {
                await alerts.fire({
                    title: 'Chưa thể chụp lại',
                    text: error.message || 'Vui lòng thử lại.',
                    icon: 'error',
                    confirmButtonColor: '#059669',
                });
            } finally {
                if (!submitted) {
                    pending = false;
                    button.disabled = false;
                }
            }
        });
    });
}

export function initializeGameImageOrderControls(root, alerts = Swal, request = fetch, reload = () => window.location.reload()) {
    root.querySelectorAll('[data-game-image-order-control]').forEach((button) => {
        if (button.dataset.controlInitialized === 'true') return;
        button.dataset.controlInitialized = 'true';
        let busy = false;
        button.addEventListener('click', async () => {
            if (busy || button.disabled) return;
            busy = true;
            button.disabled = true;
            const action = button.dataset.gameImageOrderControl;
            const money = (value) => Number(value).toLocaleString('vi-VN') + 'đ';
            const title = { cancel: 'Huỷ đơn chụp ảnh?', pause: 'Tạm dừng đơn chụp ảnh?', resume: 'Tiếp tục chụp ảnh?' }[action];
            const text =
                action === 'cancel'
                    ? `Phí huỷ ${money(button.dataset.fee)}, hoàn ${money(button.dataset.refund)} từ số tiền đã trả. Không trừ thêm tiền trong ví.`
                    : action === 'pause'
                      ? 'Đơn sẽ ngừng nhận chụp và giữ khoản đã thanh toán. Bạn có thể sửa tài khoản, mật khẩu, server rồi bấm Tiếp tục chụp.'
                      : 'Đưa đơn trở lại hàng chờ bằng thông tin tài khoản đang lưu. Không thu thêm phí.';
            let submitted = false;
            try {
                const result = await alerts.fire({
                    title,
                    text,
                    icon: action === 'cancel' ? 'warning' : 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Xác nhận',
                    cancelButtonText: 'Đóng',
                    confirmButtonColor: '#059669',
                    focusCancel: true,
                });
                if (!result.isConfirmed) return;
                const response = await request(button.dataset.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': root.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ action, capture_version: Number(button.dataset.version) }),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'Không thực hiện được thao tác.');
                submitted = true;
                reload();
            } catch (error) {
                await alerts.fire({ title: 'Chưa thể cập nhật đơn', text: error.message || 'Vui lòng thử lại.', icon: 'error' });
            } finally {
                if (!submitted) {
                    busy = false;
                    button.disabled = false;
                }
            }
        });
    });
}

export function initializeGameImageAccountDetails(root, request = fetch, reload = () => window.location.reload()) {
    const dialog = root.querySelector?.('[data-game-image-details-modal]');
    const section = root.querySelector?.('[data-game-image-accounts]');
    if (!dialog?.showModal || !section || dialog.dataset.initialized === 'true') return;
    dialog.dataset.initialized = 'true';
    const form = dialog.querySelector('[data-game-image-details-form]');
    const username = form.elements.namedItem('username');
    const server = form.elements.namedItem('server');
    const password = form.elements.namedItem('password');
    const message = dialog.querySelector('[data-game-image-details-error]');
    const eye = dialog.querySelector('[data-game-image-details-eye]');
    let target;
    let busy = false;
    let overflow = '';
    const close = () => {
        if (!busy && dialog.open) dialog.close();
    };
    const setBusy = (value) => {
        busy = value;
        form.querySelectorAll('button, input, select').forEach((input) => {
            input.disabled = value;
        });
    };
    section.addEventListener('click', (event) => {
        const button = event.target.closest('[data-game-image-edit-details]');
        if (!button || button.disabled || busy || dialog.open) return;
        target = button;
        username.value = button.dataset.username;
        server.querySelector('[data-legacy-server]')?.remove();
        if (![...server.options].some((option) => option.value === button.dataset.server)) {
            const option = root.createElement('option');
            option.value = button.dataset.server;
            option.textContent = button.dataset.server;
            option.dataset.legacyServer = 'true';
            server.appendChild(option);
        }
        server.value = button.dataset.server;
        password.value = '';
        password.type = 'password';
        eye.textContent = 'Hiện';
        eye.setAttribute('aria-pressed', 'false');
        message.textContent = '';
        overflow = root.documentElement.style.overflow;
        root.documentElement.style.overflow = 'hidden';
        dialog.showModal();
        username.focus();
    });
    eye.addEventListener('click', () => {
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        eye.textContent = show ? 'Ẩn' : 'Hiện';
        eye.setAttribute('aria-pressed', String(show));
    });
    dialog.querySelectorAll('[data-game-image-details-close]').forEach((button) => button.addEventListener('click', close));
    dialog.addEventListener('cancel', (event) => {
        if (busy) event.preventDefault();
    });
    dialog.addEventListener('close', () => {
        password.value = '';
        message.textContent = '';
        root.documentElement.style.overflow = overflow;
        if (target?.isConnected) target.focus({ preventScroll: true });
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || !target || !form.reportValidity()) return;
        const payload = {
            username: username.value,
            server: server.value,
            capture_version: Number(target.dataset.version),
            ...(password.value ? { password: password.value } : {}),
        };
        setBusy(true);
        message.textContent = '';
        try {
            const response = await request(target.dataset.url, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': root.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'Chưa lưu được thông tin tài khoản.');
            password.value = '';
            setBusy(false);
            close();
            reload();
        } catch (error) {
            message.textContent = error.message || 'Vui lòng thử lại.';
        } finally {
            setBusy(false);
        }
    });
}
