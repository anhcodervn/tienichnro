export function initializeClientCatalogModals(root = document) {
    for (const kind of ['tools', 'services']) {
        const modal = root.querySelector(`[data-client-${kind}-modal]`);
        if (!(modal instanceof HTMLDialogElement)) continue;

        let opener = null;
        let previousOverflow = '';
        const openModal = (event) => {
            event.preventDefault();
            if (modal.open) return;
            opener = event.currentTarget;
            root.querySelectorAll('details[data-client-menu][open]').forEach((menu) => {
                menu.open = false;
            });
            previousOverflow = root.documentElement.style.overflow;
            root.documentElement.style.overflow = 'hidden';
            modal.showModal();
        };

        root.querySelectorAll(`[data-client-${kind}-open]`).forEach((trigger) => {
            trigger.addEventListener('click', openModal);
            trigger.addEventListener('keydown', (event) => {
                if (event.key === ' ') openModal(event);
            });
        });
        modal.querySelector(`[data-client-${kind}-close]`)?.addEventListener('click', () => modal.close());
        modal.addEventListener('click', (event) => {
            if (event.target !== modal) return;
            const bounds = modal.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
                modal.close();
            }
        });
        modal.addEventListener('close', () => {
            root.documentElement.style.overflow = previousOverflow;
            if (opener instanceof HTMLElement) opener.focus({ preventScroll: true });
        });
    }
}
