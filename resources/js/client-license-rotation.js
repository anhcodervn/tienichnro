import Swal from 'sweetalert2';

export function initializeLicenseRotation(root = document, alerts = Swal) {
    for (const form of root.querySelectorAll('[data-license-rotate]')) {
        if (form.dataset.rotationInitialized === 'true') continue;
        form.dataset.rotationInitialized = 'true';
        const button = form.querySelector('button[type="submit"]');
        const confirmation = form.querySelector('[name="confirm_rotation"]');
        let pending = false;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (pending) return;

            pending = true;
            button.disabled = true;
            let submitted = false;

            try {
                const result = await alerts.fire({
                    title: 'Đổi license key?',
                    text: 'Key cũ sẽ mất hiệu lực và các phiên đang sử dụng sẽ bị ngắt. Bạn cần kích hoạt lại bằng key mới. Thời hạn và trạng thái license được giữ nguyên.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Đổi key',
                    cancelButtonText: 'Hủy',
                    confirmButtonColor: '#059669',
                    focusCancel: true,
                    reverseButtons: true,
                });

                if (result.isConfirmed) {
                    confirmation.value = '1';
                    form.submit();
                    submitted = true;
                }
            } catch {
                confirmation.value = '0';
                const status = root.querySelector('[data-license-copy-status]');
                if (status) status.textContent = 'Chưa thể đổi key. Vui lòng thử lại.';
            } finally {
                if (!submitted) {
                    pending = false;
                    button.disabled = false;
                }
            }
        });
    }
}
