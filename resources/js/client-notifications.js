import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

export async function initializeClientNotifications(root = document, alerts = Swal) {
    for (const element of root.querySelectorAll('[data-client-alert]')) {
        const messages = Array.from(element.querySelectorAll('[data-alert-message]'))
            .map((message) => message.textContent.trim())
            .filter(Boolean);
        if (messages.length === 0 || element.dataset.alertShown === 'true') continue;
        element.dataset.alertShown = 'true';

        const icon = ['success', 'error', 'warning', 'info'].includes(element.dataset.alertType) ? element.dataset.alertType : 'info';
        await alerts.fire({
            icon,
            titleText: element.dataset.alertTitle || 'Thông báo',
            text: [...new Set(messages)].join('\n'),
            confirmButtonText: 'Đã hiểu',
            confirmButtonColor: '#059669',
            heightAuto: false,
            customClass: { popup: 'client-swal-popup' },
        });
    }
}
