import DOMPurify from 'dompurify';
import Swal from 'sweetalert2';
import { createNroGuestReminders } from './nro-guest-reminders.js';

export function formatNroRelativeTime(timestamp, currentTime) {
    const seconds = Math.floor((currentTime - Date.parse(timestamp)) / 1000);
    if (!Number.isFinite(seconds)) return 'Không xác định';
    if (seconds < 0) return 'Sắp tới';
    if (seconds < 60) return 'Vừa cập nhật';
    if (seconds < 3600) return `${Math.floor(seconds / 60)} phút trước`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} giờ trước`;
    return `${Math.floor(seconds / 86400)} ngày trước`;
}

export function initializeNroFilters(root) {
    const select = root.querySelector('[data-nro-type]');
    if (!select) return;
    const synchronize = () => {
        let enabled = [];
        try {
            enabled = JSON.parse(select.selectedOptions[0]?.dataset.additionalFilters || '[]');
        } catch {
            enabled = [];
        }
        if (!Array.isArray(enabled)) enabled = [];
        let hasVisibleExtras = false;
        root.querySelectorAll('[data-nro-extra]').forEach((element) => {
            const visible = enabled.includes(element.dataset.nroExtra);
            hasVisibleExtras ||= visible;
            element.hidden = !visible;
            element.querySelectorAll('select, input').forEach((input) => {
                input.disabled = !visible;
                if (!visible) input.value = '';
            });
        });
        const group = root.querySelector('[data-nro-extra-group]');
        if (group) group.hidden = !hasVisibleExtras;
    };
    select.addEventListener('change', synchronize);
    synchronize();
    return synchronize;
}

export function initializeNroNotifications(alerts = Swal) {
    const root = document.querySelector('[data-nro-clock]');
    if (!(root instanceof HTMLElement)) return;
    const synchronizeFilters = initializeNroFilters(root);

    const list = root.querySelector('[data-nro-list]');
    const status = root.querySelector('[data-nro-status]');
    const count = root.querySelector('[data-nro-count]');
    const total = root.querySelector('[data-nro-total]');
    const pagination = root.querySelector('[data-nro-pagination]');
    const limit = root.querySelector('[data-nro-limit]');
    const form = root.querySelector('[data-nro-filters]');
    const refresh = root.querySelector('[data-nro-refresh]');
    const loading = root.querySelector('[data-nro-loading]');
    const error = root.querySelector('[data-nro-error]');
    let pendingRequest = null;
    let requestNumber = 0;
    let serverTime = Date.parse(root.dataset.nroClock || '');
    let startedAt = performance.now();
    let signature = root.dataset.nroSignature;
    let source = null;
    let timer = null;
    let maintenance = false;
    let guestEnded = root.dataset.nroRealtime === 'false';
    const currentTime = () => (Number.isFinite(serverTime) ? serverTime + performance.now() - startedAt : Date.now());
    const stopGuestStream = () => {
        if (guestEnded) return;
        guestEnded = true;
        source?.close();
        source = null;
        delete root.dataset.nroStream;
        setStatus('Đã dừng cập nhật · vui lòng đăng nhập');
    };
    const guestReminders = createNroGuestReminders({
        expiresAt: root.dataset.nroGuestExpires,
        loginUrl: root.dataset.nroLoginUrl,
        clock: currentTime,
        alerts,
        browser: window,
        stopStream: stopGuestStream,
        checkMember: async () => {
            try {
                const response = await fetch(window.location.href, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const payload = await response.json();
                return payload.guest === false && payload.realtime === true;
            } catch {
                return false;
            }
        },
    });

    const showMaintenance = () => {
        maintenance = true;
        ++requestNumber;
        pendingRequest?.abort();
        pendingRequest = null;
        source?.close();
        source = null;
        window.clearInterval(timer);
        timer = null;
        delete root.dataset.nroStream;
        window.location.reload();
    };

    const updateClock = (time) => {
        const parsed = Date.parse(time);
        if (!Number.isFinite(parsed)) return;
        serverTime = parsed;
        startedAt = performance.now();
    };
    const updateCountdowns = () => {
        const time = currentTime();
        root.querySelectorAll('[data-nro-relative]').forEach((element) => {
            element.textContent = ` - ${formatNroRelativeTime(element.dataset.nroRelative || '', time)}`;
        });
        root.querySelectorAll('[data-nro-respawn]').forEach((element) => {
            const remaining = Math.ceil((Date.parse(element.dataset.nroRespawn || '') - time) / 1000);
            if (!Number.isFinite(remaining)) {
                element.textContent = 'Không xác định';
            } else if (remaining <= 0) {
                element.textContent = 'Đã đến giờ dự kiến · chờ thông báo xuất hiện';
            } else {
                const hours = Math.floor(remaining / 3600);
                const minutes = Math.floor((remaining % 3600) / 60);
                const seconds = remaining % 60;
                element.textContent = `Còn ${hours ? `${hours} giờ ` : ''}${minutes} phút ${seconds} giây`;
            }
        });
        void guestReminders.tick();
    };
    const setStatus = (message) => {
        if (status) status.textContent = message;
    };
    const applySnapshot = (snapshot) => {
        updateClock(snapshot.server_time);
        if (list && snapshot.signature !== signature && typeof snapshot.html === 'string') {
            const openIds = new Set(Array.from(list.querySelectorAll('details[open]'), (item) => item.closest('[data-notify-id]')?.dataset.notifyId));
            const focusedId = document.activeElement?.closest('[data-notify-id]')?.dataset.notifyId;
            list.innerHTML = DOMPurify.sanitize(snapshot.html);
            list.querySelectorAll('[data-notify-id]').forEach((item) => {
                const details = item.querySelector('details');
                if (details && openIds.has(item.dataset.notifyId)) details.open = true;
                if (item.dataset.notifyId === focusedId) item.querySelector('summary')?.focus({ preventScroll: true });
            });
            if (pagination && typeof snapshot.pagination === 'string') pagination.innerHTML = DOMPurify.sanitize(snapshot.pagination);
            signature = snapshot.signature;
        }
        if (count) count.textContent = String(snapshot.count);
        if (total) total.textContent = String(snapshot.total);
        if (limit) limit.textContent = String(snapshot.per_page);
        updateCountdowns();
        setStatus(guestEnded ? 'Đã dừng cập nhật · vui lòng đăng nhập' : 'Đang cập nhật trực tiếp');
    };
    const start = () => {
        if (maintenance) return;
        updateCountdowns();
        if (timer === null) timer = window.setInterval(updateCountdowns, 1000);
        if (guestEnded || guestReminders.isExpired() || !root.dataset.nroStream || source) return;
        if (!('EventSource' in window)) {
            setStatus('Trình duyệt chưa hỗ trợ cập nhật trực tiếp. Bấm Làm mới để cập nhật.');
            return;
        }
        source = new EventSource(root.dataset.nroStream);
        const connection = source;
        let checkingAvailability = false;
        connection.addEventListener('open', () => {
            if (source === connection) setStatus('Đang cập nhật trực tiếp');
        });
        connection.addEventListener('notifications', (event) => {
            if (source !== connection) return;
            try {
                applySnapshot(JSON.parse(event.data));
            } catch {
                setStatus('Không đọc được cập nhật. Bấm Làm mới để đồng bộ.');
            }
        });
        connection.addEventListener('heartbeat', (event) => {
            if (source !== connection) return;
            try {
                updateClock(JSON.parse(event.data).server_time);
                updateCountdowns();
            } catch {
                setStatus('Đang chờ cập nhật…');
            }
        });
        connection.addEventListener('maintenance', () => {
            if (source === connection) showMaintenance();
        });
        connection.addEventListener('access-expired', () => {
            if (source === connection) showMaintenance();
        });
        connection.addEventListener('guest-limit', () => {
            if (source !== connection) return;
            stopGuestStream();
            void guestReminders.expire();
        });
        connection.addEventListener('error', async () => {
            if (source !== connection || checkingAvailability) return;
            setStatus('Đang kết nối lại…');
            checkingAvailability = true;
            try {
                const response = await fetch(window.location.href, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (source !== connection) return;
                const payload = await response.json();
                if (source === connection && payload.service_maintenance === true) showMaintenance();
                else if (payload.realtime === false || response.status === 403) {
                    if (root.dataset.nroGuestExpires) {
                        stopGuestStream();
                        void guestReminders.expire();
                    } else showMaintenance();
                } else if (response.status === 429) {
                    connection.close();
                    source = null;
                    setStatus(payload.message || 'Đã đạt giới hạn kết nối. Hãy đợi rồi bấm Làm mới.');
                }
            } catch {
                if (source === connection) setStatus('Đang kết nối lại…');
            } finally {
                checkingAvailability = false;
            }
        });
    };
    const setLoading = (busy) => {
        root.setAttribute?.('aria-busy', String(busy));
        if (loading) loading.hidden = !busy;
        list?.classList?.toggle('opacity-50', busy);
        pagination?.classList?.toggle('pointer-events-none', busy);
        const submit = form?.querySelector('[type="submit"]');
        if (submit) submit.disabled = busy;
    };
    const synchronizeForm = (filters) => {
        if (!form) return;
        for (const name of ['server_id', 'code', 'boss_id', 'state', 'q', 'limit']) {
            const input = form.elements.namedItem(name);
            if (!input) continue;
            let value = String(filters[name] ?? '');
            if (name === 'server_id' && !value && filters.server_code !== undefined) {
                value = Array.from(input.options).find((option) => option.dataset.serverCode === String(filters.server_code))?.value || '';
            }
            if (name === 'limit' && value && !Array.from(input.options).some((option) => option.value === value)) {
                input.add(new Option(`${value} thông báo`, value));
            }
            input.value = value;
        }
        synchronizeFilters?.();
    };
    const loadPage = async (url, pushHistory = true, scrollToList = false) => {
        if (maintenance) return;
        const target = new URL(url, window.location.href);
        if (target.origin !== window.location.origin) return;
        const sequence = ++requestNumber;
        pendingRequest?.abort();
        const controller = new AbortController();
        pendingRequest = controller;
        source?.close();
        source = null;
        setLoading(true);
        if (error) error.hidden = true;
        setStatus('Đang tải thông báo…');
        try {
            const response = await fetch(target.href, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            const payload = await response.json().catch(() => {
                throw new Error('Không đọc được phản hồi. Bấm Làm mới để thử lại.');
            });
            if (sequence !== requestNumber) return;
            if (!response.ok) {
                if (response.status === 503 && payload.service_maintenance === true) {
                    showMaintenance();
                    return;
                }
                const message = Object.values(payload.errors || {}).flat()[0];
                throw new Error(message || payload.message || 'Không tải được thông báo. Bấm Làm mới để thử lại.');
            }
            if (
                typeof payload.data?.html !== 'string' ||
                typeof payload.data?.pagination !== 'string' ||
                typeof payload.stream_url !== 'string' ||
                typeof payload.url !== 'string'
            ) {
                throw new Error('Dữ liệu phản hồi không hợp lệ. Bấm Làm mới để thử lại.');
            }
            if (typeof payload.realtime === 'boolean') root.dataset.nroRealtime = String(payload.realtime);
            applySnapshot(payload.data);
            synchronizeForm(payload.filters || {});
            root.dataset.nroStream = payload.stream_url;
            if (refresh) refresh.href = payload.url;
            if (pushHistory && new URL(payload.url, window.location.href).href !== window.location.href)
                window.history.pushState(null, '', payload.url);
            start();
            if (scrollToList)
                list?.scrollIntoView?.({
                    behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
                    block: 'start',
                });
        } catch (failure) {
            if (sequence !== requestNumber || failure.name === 'AbortError') return;
            if (error) {
                error.textContent = failure.message || 'Không tải được thông báo. Bấm Làm mới để thử lại.';
                error.hidden = false;
            }
            start();
            setStatus('Không tải được bộ lọc mới. Đang giữ danh sách trước đó.');
        } finally {
            if (sequence === requestNumber) {
                pendingRequest = null;
                setLoading(false);
            }
        }
    };
    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        const url = new URL(form.action, window.location.href);
        url.search = new URLSearchParams(Array.from(new FormData(form)).filter(([, value]) => value !== '')).toString();
        void loadPage(url.href);
    });
    pagination?.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        void loadPage(link.href, true, true);
    });
    refresh?.addEventListener('click', (event) => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        void loadPage(refresh.href, false);
    });
    window.addEventListener('popstate', () => void loadPage(window.location.href, false));
    window.addEventListener('pagehide', () => {
        ++requestNumber;
        pendingRequest?.abort();
        pendingRequest = null;
        setLoading(false);
        source?.close();
        source = null;
        window.clearInterval(timer);
        timer = null;
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) start();
    });
    start();
}
