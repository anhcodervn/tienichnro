import { initializeGameImageAccounts } from './client-game-image-accounts.js';

export function initializeGameImageRealtime(root = document, environment = window, bindAccounts = initializeGameImageAccounts) {
    root.querySelectorAll('[data-game-image-accounts][data-stream-url]').forEach((section) => {
        if (section.dataset.realtimeInitialized === 'true') return;
        section.dataset.realtimeInitialized = 'true';
        const content = section.querySelector('[data-game-image-account-content]');
        const status = section.querySelector('[data-game-image-live-status]');
        if (!content || !status) return;
        const logModal = initializeGameImageLogModal(root, section, content);
        if (!environment.EventSource) {
            status.textContent = 'Tải lại trang để cập nhật trạng thái.';
            return;
        }
        let stream = null;
        let signature = null;
        let stopped = false;
        const close = () => {
            stream?.close();
            stream = null;
        };
        const connect = () => {
            if (stream || stopped || root.hidden) return;
            const source = new environment.EventSource(section.dataset.streamUrl);
            stream = source;
            status.textContent = 'Đang kết nối cập nhật trạng thái…';
            source.addEventListener('open', () => {
                if (stream === source) status.textContent = 'Đang cập nhật trạng thái trực tiếp';
            });
            source.addEventListener('accounts', (event) => {
                if (stream !== source) return;
                let snapshot;
                try {
                    snapshot = JSON.parse(event.data);
                } catch {
                    return;
                }
                if (typeof snapshot.html !== 'string' || typeof snapshot.signature !== 'string' || snapshot.signature === signature) return;
                const revealed = new Set(
                    Array.from(content.querySelectorAll('[data-game-image-username]'))
                        .filter((cell) => cell.querySelector('button')?.getAttribute('aria-pressed') === 'true')
                        .map((cell) => cell.dataset.username),
                );
                const logSelector = 'textarea[data-game-image-capture-log]';
                const logPositions = new Map(
                    Array.from(content.querySelectorAll(logSelector), (log) => [
                        log.dataset.gameImageCaptureLog,
                        {
                            top: log.scrollTop,
                            atBottom: log.scrollHeight - log.clientHeight - log.scrollTop <= 2,
                            focused: root.activeElement === log,
                        },
                    ]),
                );
                content.innerHTML = snapshot.html;
                signature = snapshot.signature;
                logModal.sync();
                bindAccounts(root);
                content.querySelectorAll('[data-game-image-username]').forEach((cell) => {
                    if (revealed.has(cell.dataset.username)) cell.querySelector('[data-game-image-username-toggle]')?.click();
                });
                content.querySelectorAll(logSelector).forEach((log) => {
                    const position = logPositions.get(log.dataset.gameImageCaptureLog);
                    if (!position) return;
                    if (position.focused) log.focus({ preventScroll: true });
                    log.scrollTop = position.atBottom ? log.scrollHeight - log.clientHeight : position.top;
                });
                status.textContent = 'Đang cập nhật trạng thái trực tiếp';
            });
            source.addEventListener('error', () => {
                if (stream === source) status.textContent = 'Mất kết nối, đang thử kết nối lại…';
            });
            ['access-expired', 'maintenance'].forEach((name) => {
                source.addEventListener(name, () => {
                    if (stream !== source) return;
                    logModal.close();
                    stopped = true;
                    close();
                    status.textContent = name === 'maintenance' ? 'Công cụ đang bảo trì.' : 'Phiên truy cập đã kết thúc. Vui lòng đăng nhập lại.';
                });
            });
        };
        root.addEventListener('visibilitychange', () => (root.hidden ? close() : connect()));
        environment.addEventListener('pagehide', () => {
            close();
            logModal.close();
        });
        environment.addEventListener('pageshow', connect);
        connect();
    });
}

export function initializeGameImageLogModal(root, section, content) {
    const dialog = root.querySelector?.('[data-game-image-log-modal]');
    if (!dialog) return { sync() {}, close() {} };
    const terminal = dialog.querySelector('[data-game-image-log-terminal]');
    const title = dialog.querySelector('[data-game-image-log-title]');
    let orderId;
    let previousOverflow = '';
    const close = () => {
        if (dialog.open) dialog.close();
    };
    const sync = (follow = false) => {
        if (!dialog.open) return;
        const log = [...content.querySelectorAll('[data-game-image-capture-log]')].find((item) => item.dataset.gameImageCaptureLog === orderId);
        if (!log) return;
        const atBottom = terminal.scrollHeight - terminal.clientHeight - terminal.scrollTop <= 4;
        const top = terminal.scrollTop;
        const text = log.value || 'Chưa có log chi tiết cho đơn này.';
        if (terminal.textContent !== text) terminal.textContent = text;
        terminal.scrollTop = follow || atBottom ? terminal.scrollHeight : top;
    };
    section.addEventListener('click', (event) => {
        const button = event.target.closest('[data-game-image-view-log]');
        if (!button || dialog.open) return;
        orderId = button.dataset.gameImageViewLog;
        title.textContent = `Log chụp ảnh #${orderId}`;
        previousOverflow = root.documentElement.style.overflow;
        root.documentElement.style.overflow = 'hidden';
        dialog.showModal();
        sync(true);
    });
    dialog.querySelector('[data-game-image-log-close]').addEventListener('click', close);
    dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close();
    });
    dialog.addEventListener('close', () => {
        root.documentElement.style.overflow = previousOverflow;
        terminal.textContent = '';
        const button = [...content.querySelectorAll('[data-game-image-view-log]')].find((item) => item.dataset.gameImageViewLog === orderId);
        button?.focus({ preventScroll: true });
        orderId = null;
    });
    return { sync, close };
}
