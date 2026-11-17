import Swal from 'sweetalert2';

export function initializeGameImageAccounts(root = document, alerts = Swal, request = fetch, reload = () => window.location.reload()) {
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
