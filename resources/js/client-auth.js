import axios from 'axios';
import Swal from 'sweetalert2';

export function initializeClientAuth(root = document, http = axios, alerts = Swal, navigate = (url) => window.location.assign(url)) {
    for (const form of root.querySelectorAll('[data-client-auth-form]')) {
        if (form.dataset.authInitialized === 'true') continue;
        form.dataset.authInitialized = 'true';
        let pending = false;
        const button = form.querySelector('button[type="submit"]');
        const label = button.textContent;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (pending || !form.reportValidity()) return;

            pending = true;
            button.disabled = true;
            button.textContent = 'Đang xử lý…';
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('[aria-invalid]').forEach((input) => input.removeAttribute('aria-invalid'));
            let succeeded = false;

            try {
                const { data } = await http.post(form.action, new FormData(form), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (data?.status !== true || typeof data.redirect !== 'string' || !data.redirect) {
                    throw new Error('Invalid authentication response');
                }

                succeeded = true;
                await alerts.fire({
                    icon: 'success',
                    titleText: 'Thành công',
                    text: data.message,
                    timer: 1400,
                    showConfirmButton: false,
                    heightAuto: false,
                    customClass: { popup: 'client-swal-popup' },
                });
                navigate(data.redirect);
            } catch (error) {
                succeeded = false;
                const status = error.response?.status;
                const data = error.response?.data;
                const errors = data?.errors && typeof data.errors === 'object' ? data.errors : {};
                let firstInvalid = null;
                for (const field of Object.keys(errors)) {
                    const input = form.elements.namedItem(field === 'full_name' ? 'name' : field);
                    if (!input?.setAttribute) continue;
                    input.setAttribute('aria-invalid', 'true');
                    firstInvalid ??= input;
                }

                const messages = Object.values(errors)
                    .flat()
                    .filter((message) => typeof message === 'string' && message);
                const fallback =
                    status === 419
                        ? 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.'
                        : status === 429
                          ? 'Bạn thao tác quá nhiều lần. Vui lòng đợi một lúc rồi thử lại.'
                          : 'Không thể gửi yêu cầu. Vui lòng kiểm tra kết nối rồi thử lại.';
                await alerts.fire({
                    icon: 'error',
                    titleText: 'Vui lòng thử lại',
                    text:
                        status === 419 || status === 429
                            ? fallback
                            : [...new Set(messages)].join('\n') || (status === 422 && typeof data?.message === 'string' ? data.message : fallback),
                    confirmButtonText: 'Đã hiểu',
                    confirmButtonColor: '#059669',
                    heightAuto: false,
                    customClass: { popup: 'client-swal-popup' },
                });
                firstInvalid?.focus();
            } finally {
                if (!succeeded) {
                    pending = false;
                    button.disabled = false;
                    button.textContent = label;
                    form.removeAttribute('aria-busy');
                }
            }
        });
    }
}
