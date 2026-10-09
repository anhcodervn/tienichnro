export function createNroGuestReminders({ expiresAt, loginUrl, clock, alerts, browser, stopStream, checkMember }) {
    const deadline = Number(expiresAt);
    const enabled = Number.isFinite(deadline) && deadline > 0;
    const storageKey = `nro-guest-reminders:${deadline}`;
    let count = 0;
    let busy = false;
    let member = false;
    const readCount = () => {
        try {
            count = Math.max(count, Math.min(3, Number(browser.localStorage?.getItem(storageKey)) || 0));
        } catch {
            /* Storage can be unavailable in private browsing. */
        }
    };
    const tick = async (time = clock()) => {
        if (!enabled || member) return;
        const due = Math.min(3, Math.max(0, Math.floor((time - deadline + 900000) / 300000)));
        readCount();
        if (due === 3) stopStream();
        if (busy || due <= count) return;
        busy = true;
        try {
            if (await checkMember()) {
                member = true;
                browser.location.reload();
                return;
            }
            count = due;
            try {
                browser.localStorage?.setItem(storageKey, String(count));
            } catch {
                /* Keep the in-memory count. */
            }
            const result = await alerts.fire({
                icon: 'info',
                title: 'Vui lòng đăng nhập',
                text: 'Vui lòng đăng nhập để tiếp tục sử dụng.',
                showCancelButton: true,
                confirmButtonText: 'Đăng nhập',
                cancelButtonText: 'Để sau',
                customClass: { popup: 'client-swal-popup' },
            });
            if (result.isConfirmed) browser.location.assign(loginUrl);
        } finally {
            busy = false;
        }
    };
    return { tick, expire: () => tick(deadline), isExpired: () => enabled && !member && clock() >= deadline };
}
