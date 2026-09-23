import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const formatMoney = (value) => `${new Intl.NumberFormat('vi-VN').format(Number(value || 0))}đ`;
const guestOrderHistoryKey = 'napcarot.guest-order-history.v1';
const guestOrderLifetime = 365 * 24 * 60 * 60 * 1000;
const guestOrderLimit = 30;

const initializeSeoCollapsibles = () => {
    const collapsibles = Array.from(document.querySelectorAll('[data-seo-collapsible]'));

    const refresh = (collapsible) => {
        const content = collapsible.querySelector('[data-seo-collapsible-content]');
        const toggle = collapsible.querySelector('[data-seo-collapsible-toggle]');
        const fade = collapsible.querySelector('[data-seo-collapsible-fade]');

        if (!content || !toggle) return;

        const isExpanded = collapsible.dataset.expanded === 'true';
        collapsible.dataset.collapsible = 'true';
        collapsible.dataset.expanded = 'false';
        const isOverflowing = content.scrollHeight > content.clientHeight + 1;
        collapsible.dataset.expanded = String(isExpanded);

        if (!isOverflowing) {
            delete collapsible.dataset.collapsible;
            toggle.hidden = true;
            if (fade) fade.hidden = true;
            return;
        }

        toggle.hidden = false;
        if (fade) fade.hidden = isExpanded;
    };

    collapsibles.forEach((collapsible) => {
        const toggle = collapsible.querySelector('[data-seo-collapsible-toggle]');
        const label = collapsible.querySelector('[data-seo-collapsible-label]');

        refresh(collapsible);

        toggle?.addEventListener('click', () => {
            const isExpanded = collapsible.dataset.expanded !== 'true';
            collapsible.dataset.expanded = String(isExpanded);
            toggle.setAttribute('aria-expanded', String(isExpanded));
            if (label) label.textContent = isExpanded ? 'Thu gọn' : 'Xem thêm';

            const fade = collapsible.querySelector('[data-seo-collapsible-fade]');
            if (fade) fade.hidden = isExpanded;

            if (!isExpanded) {
                window.requestAnimationFrame(() => toggle.scrollIntoView({ block: 'nearest', behavior: prefersReducedMotion ? 'auto' : 'smooth' }));
            }
        });
    });

    if (collapsibles.length > 0) {
        window.addEventListener('load', () => collapsibles.forEach(refresh), { once: true });

        let resizeFrame;
        window.addEventListener('resize', () => {
            window.cancelAnimationFrame(resizeFrame);
            resizeFrame = window.requestAnimationFrame(() => collapsibles.forEach(refresh));
        });
    }
};

initializeSeoCollapsibles();

const removeGuestOrderHistory = () => {
    try {
        window.localStorage.removeItem(guestOrderHistoryKey);
    } catch {
        // Storage can be unavailable in private or restricted browser contexts.
    }
};

const readGuestOrderHistory = () => {
    try {
        const stored = JSON.parse(window.localStorage.getItem(guestOrderHistoryKey) || 'null');
        const now = Date.now();

        if (stored?.version !== 1 || !Array.isArray(stored.orders)) return [];

        return stored.orders.filter((entry) => {
            const createdAt = Date.parse(entry?.createdAt || '');

            return (
                typeof entry?.code === 'string' &&
                /^TOP[A-Z0-9]{6,32}$/.test(entry.code) &&
                Number.isFinite(createdAt) &&
                Number.isFinite(entry.expiresAt) &&
                entry.expiresAt > now
            );
        });
    } catch {
        removeGuestOrderHistory();

        return [];
    }
};

const writeGuestOrderHistory = (orders) => {
    try {
        if (orders.length === 0) {
            window.localStorage.removeItem(guestOrderHistoryKey);
            return;
        }

        window.localStorage.setItem(guestOrderHistoryKey, JSON.stringify({ version: 1, orders: orders.slice(0, guestOrderLimit) }));
    } catch {
        // Checkout must continue even when the browser blocks local storage.
    }
};

const rememberGuestOrder = (code, createdAtValue = '') => {
    const normalizedCode = String(code || '')
        .trim()
        .toUpperCase();

    if (!/^TOP[A-Z0-9]{6,32}$/.test(normalizedCode)) return readGuestOrderHistory();

    const orders = readGuestOrderHistory();
    const existing = orders.find((entry) => entry.code === normalizedCode);
    const createdAt = Number.isFinite(Date.parse(createdAtValue)) ? createdAtValue : existing?.createdAt || new Date().toISOString();
    const entry = existing || { code: normalizedCode, createdAt, expiresAt: Date.now() + guestOrderLifetime };
    const nextOrders = [entry, ...orders.filter((item) => item.code !== normalizedCode)];

    writeGuestOrderHistory(nextOrders);

    return nextOrders;
};

const filterGuestOrderHistory = (history) => {
    const search = history?.querySelector('[data-guest-order-history-search]');
    const count = history?.querySelector('[data-guest-order-history-count]');
    const filterEmpty = history?.querySelector('[data-guest-order-history-filter-empty]');
    const rows = Array.from(history?.querySelectorAll('[data-guest-order-history-row]') || []);
    const query = search?.value.trim().toUpperCase() || '';
    let visibleRows = 0;

    rows.forEach((row) => {
        const isVisible = !query || row.dataset.guestOrderHistoryCode?.includes(query);
        row.hidden = !isVisible;
        if (isVisible) visibleRows += 1;
    });

    if (count) count.textContent = query ? `${visibleRows}/${rows.length} đơn` : `${rows.length} đơn`;
    if (filterEmpty) filterEmpty.classList.toggle('hidden', rows.length === 0 || visibleRows > 0);
};

const guestOrderStatusPresentation = (order) => {
    const paymentStatuses = {
        pending: ['Chờ thanh toán', 'border-amber-200 bg-amber-50 text-amber-700'],
        expired: ['Hết hạn', 'border-slate-200 bg-slate-100 text-slate-600'],
        cancelled: ['Đã hủy', 'border-slate-200 bg-slate-100 text-slate-600'],
        refunded: ['Đã hoàn tiền', 'border-blue-200 bg-blue-50 text-blue-700'],
    };
    const orderStatuses = {
        pending: ['Chờ xử lý', 'border-amber-200 bg-amber-50 text-amber-700'],
        processing: ['Đang xử lý', 'border-blue-200 bg-blue-50 text-blue-700'],
        completed: ['Hoàn thành', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
        failed: ['Thất bại', 'border-rose-200 bg-rose-50 text-rose-700'],
        cancelled: ['Đã hủy', 'border-slate-200 bg-slate-100 text-slate-600'],
    };

    return order.payment_status === 'paid'
        ? orderStatuses[order.order_status] || ['Không xác định', 'border-slate-200 bg-slate-50 text-slate-600']
        : paymentStatuses[order.payment_status] || ['Chờ thanh toán', 'border-amber-200 bg-amber-50 text-amber-700'];
};

const hydrateGuestOrderHistory = async (history, orders) => {
    const historyUrl = history?.dataset.guestOrderHistoryUrl;

    if (!historyUrl || orders.length === 0) return;

    try {
        const response = await fetch(historyUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ codes: orders.map((order) => order.code) }),
        });

        if (!response.ok) throw new Error('Không thể tải lịch sử đơn hàng.');

        const payload = await response.json();
        const details = new Map((payload.data?.orders || []).map((order) => [order.code, order]));

        history.querySelectorAll('[data-guest-order-history-row]').forEach((row) => {
            const detail = details.get(row.dataset.guestOrderHistoryCode);
            const account = row.querySelector('[data-guest-order-history-account]');
            const server = row.querySelector('[data-guest-order-history-server]');
            const quantity = row.querySelector('[data-guest-order-history-quantity]');
            const total = row.querySelector('[data-guest-order-history-total]');
            const status = row.querySelector('[data-guest-order-history-status]');

            if (!detail) {
                if (account) account.textContent = 'Xác minh email để xem';
                if (status) status.textContent = 'Cần xác minh';
                return;
            }

            if (account) account.textContent = detail.account || 'Không xác định';
            if (server) server.textContent = detail.server || 'Không xác định';
            if (quantity) quantity.textContent = new Intl.NumberFormat('vi-VN').format(Number(detail.quantity || 0));
            if (total) total.textContent = formatMoney(detail.total_amount);

            if (status) {
                const [label, classes] = guestOrderStatusPresentation(detail);
                status.textContent = label;
                status.className = `inline-flex rounded-[5px] border px-2.5 py-1 text-xs font-bold ${classes}`;
            }
        });
    } catch {
        history.querySelectorAll('[data-guest-order-history-status]').forEach((status) => {
            status.textContent = 'Không tải được';
        });
    }
};

const renderGuestOrderHistory = (orders) => {
    const history = document.querySelector('[data-guest-order-history]');
    const list = history?.querySelector('[data-guest-order-history-list]');
    const empty = history?.querySelector('[data-guest-order-history-empty]');
    const table = history?.querySelector('[data-guest-order-history-table]');
    const template = history?.querySelector('[data-guest-order-history-item]');
    const detailUrlTemplate = document.body.dataset.orderDetailUrlTemplate;

    if (!history || !list || !(template instanceof HTMLTemplateElement)) return;

    list.replaceChildren();

    if (orders.length === 0) {
        if (table) table.hidden = true;
        if (empty) {
            empty.hidden = false;
            history.hidden = false;
        }

        filterGuestOrderHistory(history);

        return;
    }

    if (empty) empty.hidden = true;
    if (table) table.hidden = false;

    orders.forEach((entry, index) => {
        const item = template.content.cloneNode(true);
        const row = item.querySelector('[data-guest-order-history-row]');
        const orderIndex = item.querySelector('[data-guest-order-history-index]');
        const orderCode = item.querySelector('[data-guest-order-history-code]');
        const orderTime = item.querySelector('[data-guest-order-history-time]');
        const detailTriggers = item.querySelectorAll('[data-guest-order-detail]');

        if (row) row.dataset.guestOrderHistoryCode = entry.code;
        if (orderIndex) orderIndex.textContent = String(index + 1);
        if (orderCode) orderCode.textContent = entry.code;
        if (orderTime) {
            orderTime.dateTime = entry.createdAt;
            orderTime.textContent = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(entry.createdAt));
        }
        detailTriggers.forEach((detailTrigger) => {
            if (!detailUrlTemplate) return;

            detailTrigger.dataset.orderCode = entry.code;
            detailTrigger.dataset.orderDetailUrl = detailUrlTemplate.replace('__ORDER__', encodeURIComponent(entry.code));
        });
        list.append(item);
    });

    filterGuestOrderHistory(history);
    void hydrateGuestOrderHistory(history, orders);
    history.hidden = false;
};

const initializeGuestOrderHistory = () => {
    if (document.body.dataset.authenticated === 'true') return;

    let orders = readGuestOrderHistory();
    const orderMarker = document.querySelector('[data-guest-order-code]');
    const code = orderMarker?.dataset.guestOrderCode?.trim().toUpperCase() || '';

    if (/^TOP[A-Z0-9]{6,32}$/.test(code)) orders = rememberGuestOrder(code, orderMarker?.dataset.guestOrderCreatedAt || '');

    renderGuestOrderHistory(orders);
    document
        .querySelector('[data-guest-order-history-search]')
        ?.addEventListener('input', (event) => filterGuestOrderHistory(event.currentTarget.closest('[data-guest-order-history]')));
};

initializeGuestOrderHistory();

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2400,
    timerProgressBar: true,
    customClass: {
        popup: 'client-toast',
        title: 'client-toast-title',
        timerProgressBar: 'client-toast-progress',
    },
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

const notify = (icon, title) => Toast.fire({ icon, title });

const playAnimation = (element, keyframes, options = {}) => {
    if (!element || prefersReducedMotion) return null;

    element.getAnimations().forEach((animation) => animation.cancel());

    return element.animate(keyframes, {
        duration: 190,
        easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
        fill: 'both',
        ...options,
    });
};

const animateAndRelease = (element, keyframes, options = {}) => {
    const animation = playAnimation(element, keyframes, options);

    if (animation) {
        void animation.finished.then(() => animation.cancel()).catch(() => {});
    }

    return animation;
};

const initializeHomePopup = () => {
    const popup = document.querySelector('[data-home-popup]');
    const panel = popup?.querySelector('[data-home-popup-panel]');

    if (!popup || !panel) return;

    const allowDismiss = popup.dataset.dismissEnabled === 'true';
    const dismissHours = Math.max(1, Number(popup.dataset.dismissHours) || 1);
    const popupKey = String(popup.dataset.popupKey || '').replace(/[^a-z0-9-]/gi, '');
    const storageKey = popupKey ? `napcarot.home-popup.${popupKey}` : '';

    if (allowDismiss && storageKey) {
        try {
            const dismissedUntil = Number(window.localStorage.getItem(storageKey) || 0);

            if (dismissedUntil > Date.now()) return;
            window.localStorage.removeItem(storageKey);
        } catch {
            // The popup remains usable when storage is blocked by the browser.
        }
    }

    const isModal = popup.dataset.displayMode === 'modal';
    const closeButtons = popup.querySelectorAll('[data-home-popup-close]');
    const dismissButtons = popup.querySelectorAll('[data-home-popup-dismiss]');
    let isClosing = false;

    const finishClosing = () => {
        popup.hidden = true;
        popup.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('client-home-popup-open');
    };

    const closePopup = (rememberDismissal = false) => {
        if (isClosing) return;
        isClosing = true;

        if (rememberDismissal && allowDismiss && storageKey) {
            try {
                window.localStorage.setItem(storageKey, String(Date.now() + dismissHours * 60 * 60 * 1000));
            } catch {
                // Closing must still work when storage is blocked by the browser.
            }
        }

        const animation = playAnimation(
            panel,
            [
                { opacity: 1, transform: 'translateY(0) scale(1)' },
                { opacity: 0, transform: `translateY(${isModal ? '8px' : '16px'}) scale(0.98)` },
            ],
            { duration: 160 },
        );

        if (animation) {
            void animation.finished.then(finishClosing).catch(finishClosing);
            return;
        }

        finishClosing();
    };

    popup.hidden = false;
    popup.setAttribute('aria-hidden', 'false');
    if (isModal) document.body.classList.add('client-home-popup-open');

    animateAndRelease(
        panel,
        [
            { opacity: 0, transform: `translateY(${isModal ? '10px' : '18px'}) scale(0.98)` },
            { opacity: 1, transform: 'translateY(0) scale(1)' },
        ],
        { duration: 240 },
    );

    closeButtons.forEach((button) => button.addEventListener('click', () => closePopup()));
    dismissButtons.forEach((button) => button.addEventListener('click', () => closePopup(true)));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !popup.hidden) closePopup();
    });
    panel.focus({ preventScroll: true });
};

initializeHomePopup();

const copyText = async (value) => {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
        return;
    }

    const input = document.createElement('textarea');
    input.value = value;
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.append(input);
    input.select();
    document.execCommand('copy');
    input.remove();
};

document.querySelectorAll('[data-account-tabs]').forEach((tabs) => {
    const activeTab = tabs.querySelector('[data-account-tab-active]');

    if (!activeTab) return;

    requestAnimationFrame(() => {
        const tabsBounds = tabs.getBoundingClientRect();
        const activeTabBounds = activeTab.getBoundingClientRect();
        const left = tabs.scrollLeft + activeTabBounds.left - tabsBounds.left - (tabs.clientWidth - activeTab.clientWidth) / 2;
        tabs.scrollTo({ left: Math.max(0, left), behavior: 'auto' });
    });
});

document.querySelectorAll('[data-client-alert]').forEach((alert) => {
    const type = alert.dataset.alertType === 'error' ? 'error' : 'success';
    const messages = [...alert.querySelectorAll('[data-alert-message]')].map((item) => item.textContent.trim()).filter(Boolean);

    if (messages.length === 0) return;

    if (type === 'error') {
        void Swal.fire({
            icon: 'error',
            title: alert.dataset.alertTitle || 'Không thể thực hiện',
            text: messages.join(' • '),
            confirmButtonText: 'Đã hiểu',
            confirmButtonColor: '#0891b2',
            allowOutsideClick: false,
            customClass: { popup: 'client-alert-dialog' },
        });
        return;
    }

    void notify('success', messages[0]);
});

document.querySelectorAll('[data-page-enter]').forEach((page) => {
    animateAndRelease(
        page,
        [
            { opacity: 0.6, transform: 'translateY(6px)' },
            { opacity: 1, transform: 'translateY(0)' },
        ],
        { duration: 320 },
    );
});

const desktopNavMenus = [...document.querySelectorAll('[data-desktop-nav-menu]')];

desktopNavMenus.forEach((menu) => {
    menu.addEventListener('toggle', () => {
        if (!menu.open) return;

        desktopNavMenus.forEach((otherMenu) => {
            if (otherMenu !== menu) otherMenu.open = false;
        });
    });
});

document.addEventListener('click', (event) => {
    desktopNavMenus.forEach((menu) => {
        if (!menu.contains(event.target)) menu.open = false;
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    const openMenu = desktopNavMenus.find((menu) => menu.open);
    if (!openMenu) return;

    openMenu.open = false;
    openMenu.querySelector('summary')?.focus();
});

document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
    const menu = document.getElementById(button.getAttribute('aria-controls'));
    const panel = menu?.querySelector('[data-menu-panel]');
    const backdrop = menu?.querySelector('[data-menu-backdrop]');
    if (!menu || !panel || !backdrop) return;

    let transitionId = 0;
    const menuItems = () => [...panel.querySelectorAll('[data-menu-item]')];

    const focusableElements = () =>
        [
            ...menu.querySelectorAll(
                'a[href]:not([tabindex="-1"]), button:not([disabled]):not([tabindex="-1"]), input:not([disabled]):not([tabindex="-1"]), [tabindex]:not([tabindex="-1"])',
            ),
        ].filter((element) => !element.hidden && element.tabIndex >= 0);

    const setExpanded = async (expanded, { animate = true, restoreFocus = true } = {}) => {
        const currentTransition = ++transitionId;
        button.setAttribute('aria-expanded', String(expanded));
        button.setAttribute('aria-label', expanded ? 'Đóng menu' : 'Mở menu');

        if (expanded) {
            menu.hidden = false;
            menu.setAttribute('aria-hidden', 'false');
            document.body.classList.add('client-menu-open');

            animateAndRelease(button, [{ transform: 'scale(0.92)' }, { transform: 'scale(1)' }], { duration: 220 });
            menuItems().forEach((item, index) => {
                animateAndRelease(
                    item,
                    [
                        { opacity: 0, transform: 'translateX(14px)' },
                        { opacity: 1, transform: 'translateX(0)' },
                    ],
                    { duration: 230, delay: 70 + index * 35 },
                );
            });

            const animations = animate
                ? [
                      playAnimation(backdrop, [{ opacity: 0 }, { opacity: 1 }], { duration: 240 }),
                      playAnimation(
                          panel,
                          [
                              { opacity: 0.82, transform: 'translateX(100%) scale(0.985)' },
                              { opacity: 1, transform: 'translateX(0) scale(1)' },
                          ],
                          { duration: 300 },
                      ),
                  ]
                : [];

            await Promise.all(animations.filter(Boolean).map((animation) => animation.finished.catch(() => {})));
            animations.filter(Boolean).forEach((animation) => animation.cancel());

            if (currentTransition === transitionId) {
                menu.querySelector('[data-menu-close]:not([tabindex="-1"])')?.focus();
            }

            return;
        }

        const animations =
            !menu.hidden && animate
                ? [
                      playAnimation(backdrop, [{ opacity: 1 }, { opacity: 0 }], { duration: 190 }),
                      playAnimation(
                          panel,
                          [
                              { opacity: 1, transform: 'translateX(0) scale(1)' },
                              { opacity: 0.85, transform: 'translateX(100%) scale(0.99)' },
                          ],
                          { duration: 240 },
                      ),
                  ]
                : [];

        await Promise.all(animations.filter(Boolean).map((animation) => animation.finished.catch(() => {})));
        animations.filter(Boolean).forEach((animation) => animation.cancel());

        if (currentTransition !== transitionId) return;

        menu.hidden = true;
        menu.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('client-menu-open');

        if (restoreFocus) button.focus();
    };

    const resetMenu = () => {
        transitionId += 1;
        menu.getAnimations({ subtree: true }).forEach((animation) => animation.cancel());
        menu.hidden = true;
        menu.setAttribute('aria-hidden', 'true');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', 'Mở menu');
        document.body.classList.remove('client-menu-open');
    };

    button.addEventListener('click', () => {
        void setExpanded(button.getAttribute('aria-expanded') !== 'true');
    });

    menu.querySelectorAll('[data-menu-close]').forEach((closeButton) => {
        closeButton.addEventListener('click', () => void setExpanded(false));
    });

    menu.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => void setExpanded(false, { restoreFocus: false }));
    });

    menu.querySelectorAll('[data-game-picker-open]').forEach((gamePickerButton) => {
        gamePickerButton.addEventListener('click', () => void setExpanded(false, { restoreFocus: false }));
    });

    document.addEventListener('keydown', (event) => {
        if (button.getAttribute('aria-expanded') !== 'true') return;

        if (event.key === 'Escape') {
            void setExpanded(false);
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = focusableElements();
        const first = focusable[0];
        const last = focusable.at(-1);

        if (!first || !last) return;

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
        if (event.matches && button.getAttribute('aria-expanded') === 'true') {
            void setExpanded(false, { animate: false, restoreFocus: false }).then(() => {
                document.querySelector('[data-client-home]')?.focus({ preventScroll: true });
            });
        }
    });

    window.addEventListener('pagehide', resetMenu);
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) resetMenu();
    });
});

const gamePickerModal = document.querySelector('[data-game-picker-modal]');

if (gamePickerModal) {
    const gamePickerPanel = gamePickerModal.querySelector('[data-game-picker-panel]');
    const gamePickerBackdrop = gamePickerModal.querySelector('[data-game-picker-backdrop]');
    const gamePickerTriggers = [...document.querySelectorAll('[data-game-picker-open]')];
    let gamePickerReturnFocus = null;

    const gamePickerFocusableElements = () =>
        [
            ...gamePickerModal.querySelectorAll(
                'a[href]:not([tabindex="-1"]), button:not([disabled]):not([tabindex="-1"]), [tabindex]:not([tabindex="-1"])',
            ),
        ].filter((element) => !element.hidden && element.tabIndex >= 0);

    const setGamePickerExpanded = (expanded) => {
        gamePickerTriggers.forEach((trigger) => trigger.setAttribute('aria-expanded', String(expanded)));
    };

    const closeGamePicker = ({ restoreFocus = true } = {}) => {
        if (gamePickerModal.hidden) return;

        gamePickerModal.hidden = true;
        gamePickerModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('client-game-picker-open');
        setGamePickerExpanded(false);

        if (restoreFocus && gamePickerReturnFocus?.isConnected) {
            gamePickerReturnFocus.focus({ preventScroll: true });
        }
    };

    const openGamePicker = (trigger) => {
        const openedFromMobileMenu = trigger.closest('[data-mobile-menu]');
        gamePickerReturnFocus = openedFromMobileMenu ? document.querySelector('[data-mobile-sidebar-toggle]') : trigger;
        gamePickerModal.hidden = false;
        gamePickerModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('client-game-picker-open');
        setGamePickerExpanded(true);

        animateAndRelease(gamePickerBackdrop, [{ opacity: 0 }, { opacity: 1 }], { duration: 180 });
        animateAndRelease(
            gamePickerPanel,
            [
                { opacity: 0, transform: 'translateY(-47%) scale(0.97)' },
                { opacity: 1, transform: 'translateY(-50%) scale(1)' },
            ],
            { duration: 220 },
        );
        window.requestAnimationFrame(() => gamePickerFocusableElements()[0]?.focus({ preventScroll: true }));
    };

    gamePickerTriggers.forEach((trigger) => {
        trigger.addEventListener('click', () => openGamePicker(trigger));
    });

    gamePickerModal.querySelectorAll('[data-game-picker-close]').forEach((closeButton) => {
        closeButton.addEventListener('click', () => closeGamePicker());
    });

    document.addEventListener('keydown', (event) => {
        if (gamePickerModal.hidden) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            closeGamePicker();
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = gamePickerFocusableElements();
        const first = focusable[0];
        const last = focusable.at(-1);

        if (!first || !last) return;

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    window.addEventListener('pagehide', () => closeGamePicker({ restoreFocus: false }));
}

document.querySelectorAll('[data-account-menu]').forEach((accountMenu) => {
    const button = accountMenu.querySelector('[data-account-menu-toggle]');
    const panel = accountMenu.querySelector('[data-account-menu-panel]');
    const chevron = accountMenu.querySelector('[data-account-menu-chevron]');
    if (!button || !panel) return;

    let transitionId = 0;

    const setExpanded = async (expanded, { restoreFocus = false } = {}) => {
        const currentTransition = ++transitionId;
        button.setAttribute('aria-expanded', String(expanded));
        chevron?.classList.toggle('rotate-180', expanded);

        if (expanded) {
            panel.hidden = false;
            const animation = playAnimation(
                panel,
                [
                    { opacity: 0, transform: 'translateY(-6px) scale(0.98)' },
                    { opacity: 1, transform: 'translateY(0) scale(1)' },
                ],
                { duration: 180 },
            );

            await animation?.finished.catch(() => {});
            animation?.cancel();

            return;
        }

        const animation = panel.hidden
            ? null
            : playAnimation(
                  panel,
                  [
                      { opacity: 1, transform: 'translateY(0) scale(1)' },
                      { opacity: 0, transform: 'translateY(-4px) scale(0.985)' },
                  ],
                  { duration: 140 },
              );

        await animation?.finished.catch(() => {});
        animation?.cancel();

        if (currentTransition !== transitionId) return;

        panel.hidden = true;
        if (restoreFocus) button.focus();
    };

    button.addEventListener('click', () => {
        void setExpanded(button.getAttribute('aria-expanded') !== 'true');
    });

    button.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowDown') return;

        event.preventDefault();
        void setExpanded(true).then(() => panel.querySelector('a[href], button:not([disabled])')?.focus());
    });

    document.addEventListener('click', (event) => {
        if (button.getAttribute('aria-expanded') === 'true' && !accountMenu.contains(event.target)) {
            void setExpanded(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
            void setExpanded(false, { restoreFocus: true });
        }
    });

    accountMenu.addEventListener('focusout', () => {
        window.setTimeout(() => {
            if (!accountMenu.contains(document.activeElement) && button.getAttribute('aria-expanded') === 'true') {
                void setExpanded(false);
            }
        });
    });

    window.addEventListener('pagehide', () => {
        transitionId += 1;
        panel.getAnimations({ subtree: true }).forEach((animation) => animation.cancel());
        panel.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        chevron?.classList.remove('rotate-180');
    });
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest?.('[data-copy]');

    if (!button) return;

    const original = button.innerHTML;

    try {
        button.disabled = true;
        await copyText(button.dataset.copy || '');
        button.textContent = 'Đã sao chép';
        playAnimation(button, [{ transform: 'scale(0.96)' }, { transform: 'scale(1)' }], { duration: 160 });
        void notify('success', 'Đã sao chép vào bộ nhớ tạm');
    } catch {
        void notify('error', 'Không thể sao chép. Vui lòng thử lại.');
    } finally {
        window.setTimeout(() => {
            button.innerHTML = original;
            button.disabled = false;
        }, 1400);
    }
});

const orderDetailModal = document.querySelector('[data-order-detail-modal]');

if (orderDetailModal) {
    const panel = orderDetailModal.querySelector('[data-order-detail-panel]');
    const title = orderDetailModal.querySelector('[data-order-detail-title]');
    const loading = orderDetailModal.querySelector('[data-order-detail-loading]');
    const content = orderDetailModal.querySelector('[data-order-detail-content]');
    const errorBox = orderDetailModal.querySelector('[data-order-detail-error]');
    const errorMessage = orderDetailModal.querySelector('[data-order-detail-error-message]');
    const retryButton = orderDetailModal.querySelector('[data-order-detail-retry]');
    const unlockForm = document.querySelector('[data-order-history-unlock]');
    let activeTrigger = null;
    let currentCode = '';
    let currentDetailUrl = '';
    let refreshTimer = null;

    const stopRefresh = () => {
        if (refreshTimer) window.clearTimeout(refreshTimer);
        refreshTimer = null;
    };

    const closeOrderDetail = () => {
        stopRefresh();
        orderDetailModal.hidden = true;
        orderDetailModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        activeTrigger?.focus?.();
    };

    const scheduleRefresh = () => {
        stopRefresh();

        if (content.querySelector('[data-order-detail-state]')?.dataset.orderTerminal === 'true') return;

        refreshTimer = window.setTimeout(() => void loadOrderDetail(currentDetailUrl, true), 10000);
    };

    const showOrderDetailError = (message, requiresVerification = false) => {
        loading.hidden = true;
        content.replaceChildren();
        errorBox.classList.remove('hidden');
        errorBox.dataset.requiresVerification = String(requiresVerification);
        errorMessage.textContent = message;
        retryButton.textContent = requiresVerification ? 'Xác minh email' : 'Thử lại';
    };

    const loadOrderDetail = async (detailUrl, silent = false) => {
        if (!detailUrl) return;

        currentDetailUrl = detailUrl;
        stopRefresh();

        if (!silent) {
            loading.hidden = false;
            content.replaceChildren();
            errorBox.classList.add('hidden');
        }

        try {
            const response = await fetch(detailUrl, { credentials: 'same-origin', headers: { Accept: 'text/html' } });

            if (response.status === 403) {
                showOrderDetailError('Phiên xem đơn đã hết hạn. Vui lòng xác minh lại mã đơn và email.', true);
                return;
            }

            if (!response.ok) throw new Error('Không thể tải thông tin đơn hàng.');

            content.innerHTML = await response.text();
            loading.hidden = true;
            errorBox.classList.add('hidden');
            scheduleRefresh();
        } catch (error) {
            if (silent) {
                scheduleRefresh();
                return;
            }

            showOrderDetailError(error.message || 'Không thể tải thông tin đơn hàng.');
        }
    };

    const openOrderDetail = (detailUrl, code, trigger = null) => {
        activeTrigger = trigger;
        currentCode = code || '';
        title.textContent = currentCode ? `Chi tiết ${currentCode}` : 'Chi tiết và tiến độ';
        orderDetailModal.hidden = false;
        orderDetailModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        panel.focus({ preventScroll: true });
        void loadOrderDetail(detailUrl);
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('[data-order-detail-trigger]');

        if (!trigger || !trigger.dataset.orderDetailUrl) return;

        openOrderDetail(trigger.dataset.orderDetailUrl, trigger.dataset.orderCode, trigger);
    });

    orderDetailModal.querySelectorAll('[data-order-detail-close]').forEach((button) => button.addEventListener('click', closeOrderDetail));
    retryButton.addEventListener('click', () => {
        if (errorBox.dataset.requiresVerification === 'true' && unlockForm) {
            const codeInput = unlockForm.querySelector('[name="code"]');
            const emailInput = unlockForm.querySelector('[name="email"]');
            if (codeInput) codeInput.value = currentCode;
            closeOrderDetail();
            unlockForm.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'center' });
            window.setTimeout(() => emailInput?.focus(), prefersReducedMotion ? 0 : 350);
            return;
        }

        void loadOrderDetail(currentDetailUrl);
    });

    document.addEventListener('keydown', (event) => {
        if (orderDetailModal.hidden || event.key !== 'Escape') return;
        closeOrderDetail();
    });

    unlockForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitButton = unlockForm.querySelector('[type="submit"]');
        const unlockError = unlockForm.querySelector('[data-order-history-unlock-error]');
        const formData = new FormData(unlockForm);
        submitButton.disabled = true;
        unlockError.classList.add('hidden');

        try {
            const response = await fetch(unlockForm.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: formData,
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok || payload.status !== true) {
                const validationMessage = Object.values(payload.errors || {}).flat()[0];
                throw new Error(validationMessage || 'Không tìm thấy đơn hàng khớp mã và email.');
            }

            const order = payload.data;
            const orders = rememberGuestOrder(order.code, order.created_at);
            renderGuestOrderHistory(orders);
            unlockForm.reset();
            openOrderDetail(order.detail_url, order.code, submitButton);
        } catch (error) {
            unlockError.textContent = error.message || 'Không thể xác minh đơn hàng.';
            unlockError.classList.remove('hidden');
        } finally {
            submitButton.disabled = false;
        }
    });

    window.addEventListener('pagehide', stopRefresh);
}

document.querySelectorAll('[data-topup-form]').forEach((form) => {
    const stepLayout = form.dataset.stepLayout === 'true';
    const game = form.querySelector('[name="game_id"]');
    const server = form.querySelector('[name="server_id"]');
    const serverPickers = Array.from(form.querySelectorAll('[data-server-picker]'));
    const topupPackage = form.querySelector('[name="package_id"]');
    const purchaseMode = form.querySelector('[data-purchase-mode]');
    const purchaseTabs = Array.from(form.querySelectorAll('[data-purchase-tab]'));
    const purchasePanels = Array.from(form.querySelectorAll('[data-purchase-panel]'));
    const recipientFieldGroups = Array.from(form.querySelectorAll('[data-recipient-fields]'));
    const recipientInputs = Array.from(form.querySelectorAll('[data-recipient-input]'));
    const bulkSchemas = Array.from(form.querySelectorAll('[data-bulk-schema]'));
    const bulkRecipients = form.querySelector('[data-bulk-recipients]');
    const bulkAccountCount = form.querySelector('[data-bulk-account-count]');
    const bulkCount = form.querySelector('[data-bulk-count]');
    const bulkFormatError = form.querySelector('[data-bulk-format-error]');
    const singleQuantity = form.querySelector('[data-single-quantity]');
    const quantityDecrease = form.querySelector('[data-quantity-decrease]');
    const quantityIncrease = form.querySelector('[data-quantity-increase]');
    const packageGroups = Array.from(form.querySelectorAll('[data-package-options]'));
    const packageButtons = Array.from(form.querySelectorAll('[data-package-button]'));
    const total = form.querySelector('[data-order-total]');
    const discount = form.querySelector('[data-order-discount]');
    const summaryPackage = form.querySelector('[data-summary-package]');
    const summaryQuantityLabel = form.querySelector('[data-summary-quantity-label]');
    const summaryQuantity = form.querySelector('[data-summary-quantity]');
    const summaryOriginal = form.querySelector('[data-summary-original]');
    const summaryRewards = form.querySelector('[data-summary-rewards]');
    const summaryReward = form.querySelector('[data-summary-reward]');
    const summaryRewardX2 = form.querySelector('[data-summary-reward-x2]');
    const summaryRewardX3 = form.querySelector('[data-summary-reward-x3]');
    const submitButton = form.querySelector('[data-submit-button]');
    const submitText = form.querySelector('[data-submit-text]');
    const paymentMethod = form.querySelector('[data-payment-method]');
    const walletOption = paymentMethod?.querySelector('option[value="wallet"]');
    const paymentButtons = Array.from(form.querySelectorAll('[data-payment-option]'));
    const paymentHelp = form.querySelector('[data-payment-help]');
    let preferredPaymentMethod = paymentMethod?.value || 'bank_transfer';
    let paymentChoiceTouched = paymentMethod?.dataset.paymentExplicit === 'true';
    const offerPanels = document.querySelectorAll('[data-game-offer]');
    const rewardPanels = document.querySelectorAll('[data-game-reward]');
    const rewardTabs = Array.from(document.querySelectorAll('[data-game-reward-tab]'));
    const confirmationModal = form.querySelector('[data-topup-confirmation-modal]');
    const confirmationCheckbox = form.querySelector('[data-topup-confirmation-checkbox]');
    const confirmationSubmit = form.querySelector('[data-topup-confirmation-submit]');
    const confirmationSubmitText = form.querySelector('[data-topup-confirmation-submit-text]');
    const confirmationGame = form.querySelector('[data-confirm-game]');
    const confirmationPackage = form.querySelector('[data-confirm-package]');
    const confirmationServer = form.querySelector('[data-confirm-server]');
    const confirmationRecipientLabel = form.querySelector('[data-confirm-recipient-label]');
    const confirmationRecipients = form.querySelector('[data-confirm-recipients]');
    const confirmationTotal = form.querySelector('[data-confirm-total]');
    let confirmationGranted = false;
    let confirmationPreviouslyFocused = null;

    const syncGameName = () => {
        const selectedGame = game?.selectedOptions[0];
        const gameName = selectedGame?.dataset.name || selectedGame?.textContent?.trim() || 'Chọn game';

        document.querySelectorAll('[data-selected-game-name]').forEach((element) => {
            element.textContent = gameName;
            playAnimation(element, [
                { opacity: 0.45, transform: 'translateY(2px)' },
                { opacity: 1, transform: 'translateY(0)' },
            ]);
        });
    };

    const syncOfferPanel = () => {
        const gameId = game?.value || '';

        offerPanels.forEach((panel) => {
            const shouldShow = panel.dataset.gameOffer === gameId;

            if (shouldShow && panel.hidden) {
                panel.hidden = false;
                playAnimation(
                    panel,
                    [
                        { opacity: 0, transform: 'translateX(8px)' },
                        { opacity: 1, transform: 'translateX(0)' },
                    ],
                    {
                        duration: 230,
                    },
                );
            } else if (!shouldShow && !panel.hidden) {
                const animation = playAnimation(panel, [{ opacity: 1 }, { opacity: 0 }], { duration: 110 });

                if (animation) {
                    void animation.finished
                        .then(() => {
                            if (game?.value !== panel.dataset.gameOffer) panel.hidden = true;
                            animation.cancel();
                        })
                        .catch(() => {});
                } else {
                    panel.hidden = true;
                }
            }
        });

        rewardPanels.forEach((panel) => {
            panel.hidden = panel.dataset.gameReward !== gameId;
        });

        rewardTabs.forEach((tab) => {
            const isSelected = tab.dataset.gameRewardTab === gameId;
            tab.setAttribute('aria-selected', String(isSelected));
            tab.tabIndex = isSelected ? 0 : -1;
        });
    };

    const syncPackageSelection = () => {
        const packageId = topupPackage?.value || '';
        const denomination = topupPackage?.selectedOptions[0]?.dataset.denomination || '';

        packageButtons.forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.packageButton === packageId));
        });

        document.querySelectorAll('[data-package-row], [data-package-column]').forEach((element) => {
            const packageReference = element.dataset.packageRow || element.dataset.packageColumn;
            const selected = packageReference === packageId;
            element.classList.toggle('is-selected', selected);
            if (selected)
                playAnimation(element, [{ backgroundColor: 'rgb(236 254 255)' }, { backgroundColor: 'rgb(207 250 254)' }], { duration: 280 });
        });

        document.querySelectorAll('[data-reward-denomination]').forEach((element) => {
            const selected = element.dataset.rewardDenomination === denomination;
            element.classList.toggle('is-selected', selected);
            if (selected)
                playAnimation(element, [{ backgroundColor: 'rgb(236 254 255)' }, { backgroundColor: 'rgb(207 250 254)' }], { duration: 280 });
        });
    };

    const syncPackageCards = () => {
        const gameId = game?.value || '';

        packageGroups.forEach((group) => {
            group.hidden = group.dataset.packageOptions !== gameId;
        });

        packageButtons.forEach((button) => {
            const group = button.closest('[data-package-options]');
            const matchesGame = group?.dataset.packageOptions === gameId;
            button.hidden = !matchesGame;
        });
    };

    const updateQuantityLimits = () => {
        const option = topupPackage?.selectedOptions[0];
        const minimum = Number(option?.dataset.min || 1);
        const maximum = Number(option?.dataset.max || 10);

        if (singleQuantity) {
            singleQuantity.min = String(minimum);
            singleQuantity.max = String(maximum);
            singleQuantity.value = String(Math.min(maximum, Math.max(minimum, Number(singleQuantity.value || minimum))));
        }
    };

    const compileRecipientRegex = (source) => {
        if (!source) return null;

        try {
            return new RegExp(source, 'u');
        } catch {
            return false;
        }
    };

    const validateRecipientInput = (input) => {
        const errorElement = input.closest('[data-field-key]')?.querySelector('[data-recipient-format-error]');
        const regex = compileRecipientRegex(input.dataset.validationRegex || '');
        const label = input.dataset.validationLabel || 'Trường dữ liệu';
        const value = input.value.trim();
        let message = '';

        if (!input.disabled && value !== '' && regex === false) {
            message = `${label} có cấu hình regex không hợp lệ.`;
        } else if (!input.disabled && value !== '' && regex && !regex.test(value)) {
            message = `${label} không đúng định dạng.`;
        }

        input.setCustomValidity(message);
        input.setAttribute('aria-invalid', String(message !== ''));
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.hidden = message === '';
        }

        return message === '';
    };

    const activeBulkFields = () => {
        const activeSchema = bulkSchemas.find((schema) => !schema.hidden);

        try {
            const fields = JSON.parse(activeSchema?.dataset.bulkFields || '[]');

            return Array.isArray(fields) ? fields : [];
        } catch {
            return [];
        }
    };

    const countBulkRecipients = () => {
        const lines = (bulkRecipients?.value || '').split(/\r\n|\r|\n/).filter((line) => line.trim() !== '');
        const fields = activeBulkFields();
        const maximumColumnCount = fields.length + 1;
        let quantity = 0;
        let validAccountCount = 0;
        let firstInvalidLine = 0;
        let firstInvalidAccount = '';
        let formatMessage = '';
        const option = topupPackage?.selectedOptions[0];
        const minimumQuantity = Number(option?.dataset.min || 1);
        const maximumQuantity = Number(option?.dataset.max || 10);

        lines.forEach((line, index) => {
            if (firstInvalidLine > 0) return;

            const values = line.split('|');
            const account = values[0]?.trim() || '';
            const quantityValue = values[values.length - 1]?.trim() || '';
            const parsedQuantity = Number(quantityValue);
            const hasValidFormat =
                line.includes('|') &&
                account !== '' &&
                /^[1-9]\d*$/.test(quantityValue) &&
                parsedQuantity >= minimumQuantity &&
                parsedQuantity <= maximumQuantity &&
                values.length <= maximumColumnCount;

            if (!hasValidFormat) {
                if (firstInvalidLine === 0) {
                    firstInvalidLine = index + 1;
                    firstInvalidAccount = account || `ở dòng ${index + 1}`;
                    formatMessage = `Tài khoản ${firstInvalidAccount} định dạng không hợp lệ. Vui lòng nhập đúng định dạng param|số lượng.`;
                }

                return;
            }

            const recipientValues = values.slice(0, -1);
            const fieldError = fields.find((field, fieldIndex) => {
                const value = recipientValues[fieldIndex]?.trim() || '';
                const regex = compileRecipientRegex(field.regex || '');

                if (field.required && value === '') {
                    formatMessage = `Dòng ${index + 1}, cột ${fieldIndex + 1} (${field.label}): không được để trống.`;

                    return true;
                }

                if (value.length > 191) {
                    formatMessage = `Dòng ${index + 1}, cột ${fieldIndex + 1} (${field.label}): không được vượt quá 191 ký tự.`;

                    return true;
                }

                if (value !== '' && field.type === 'select') {
                    const allowedValues = Array.isArray(field.options) ? field.options.map((option) => String(option.value)) : [];

                    if (!allowedValues.includes(value)) {
                        formatMessage = `Dòng ${index + 1}, cột ${fieldIndex + 1} (${field.label}): không thuộc danh sách lựa chọn hợp lệ.`;

                        return true;
                    }
                }

                if (value !== '' && field.type === 'number') {
                    if (!/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(value)) {
                        formatMessage = `Dòng ${index + 1}, cột ${fieldIndex + 1} (${field.label}): phải là một số.`;

                        return true;
                    }

                    const numericValue = Number(value);
                    if (field.min !== null && field.min !== undefined && numericValue < Number(field.min)) {
                        formatMessage = `Dòng ${index + 1}, cột ${fieldIndex + 1} (${field.label}): phải lớn hơn hoặc bằng ${field.min}.`;

                        return true;
                    }

                    if (field.max !== null && field.max !== undefined && numericValue > Number(field.max)) {
                        formatMessage = `Dòng ${index + 1}, cột ${fieldIndex + 1} (${field.label}): phải nhỏ hơn hoặc bằng ${field.max}.`;

                        return true;
                    }

                    if (field.step !== null && field.step !== undefined) {
                        const step = Number(field.step);
                        const stepBase = field.min === null || field.min === undefined ? 0 : Number(field.min);
                        const stepOffset = (numericValue - stepBase) / step;

                        if (Math.abs(stepOffset - Math.round(stepOffset)) > 0.000000001) {
                            formatMessage = `Dòng ${index + 1}, cột ${fieldIndex + 1} (${field.label}): phải theo bước ${field.step}.`;

                            return true;
                        }
                    }
                }

                if (value !== '' && regex === false) {
                    formatMessage = `Dòng ${index + 1}, cột ${fieldIndex + 1} (${field.label}): cấu hình regex không hợp lệ.`;

                    return true;
                }

                if (value !== '' && regex && !regex.test(value)) {
                    formatMessage = `Dòng ${index + 1}, cột ${fieldIndex + 1} (${field.label}): không đúng định dạng.`;

                    return true;
                }

                return false;
            });

            if (fieldError) {
                if (firstInvalidLine === 0) firstInvalidLine = index + 1;

                return;
            }

            validAccountCount += 1;
            quantity += parsedQuantity;
        });

        const hasInvalidRows = firstInvalidLine > 0;
        if (!hasInvalidRows) formatMessage = '';

        if (bulkAccountCount) bulkAccountCount.textContent = String(validAccountCount);
        if (bulkCount) bulkCount.textContent = hasInvalidRows ? '—' : String(quantity);
        if (bulkFormatError) {
            bulkFormatError.textContent = formatMessage;
            bulkFormatError.hidden = !hasInvalidRows;
        }
        bulkRecipients?.setCustomValidity(formatMessage);
        bulkRecipients?.setAttribute('aria-invalid', String(hasInvalidRows));

        return { quantity, hasInvalidRows, firstInvalidLine };
    };

    const syncPaymentButtons = () => {
        paymentButtons.forEach((button) => {
            const isSelected = paymentMethod?.value === button.dataset.paymentOption;
            const isWalletUnavailable = button.dataset.paymentOption === 'wallet' && (!walletOption || walletOption.disabled);

            button.disabled = isWalletUnavailable;
            button.setAttribute('aria-checked', String(isSelected));
            button.tabIndex = isSelected ? 0 : -1;
        });
    };

    const syncPaymentMethod = (paymentTotal, hasPackage) => {
        const walletBalance = Number(walletOption?.dataset.walletBalance || 0);
        const canPayWithWallet = Boolean(walletOption) && hasPackage && walletBalance >= paymentTotal;

        if (walletOption) walletOption.disabled = !canPayWithWallet;
        if (paymentMethod) {
            paymentMethod.value = canPayWithWallet && (!paymentChoiceTouched || preferredPaymentMethod === 'wallet') ? 'wallet' : 'bank_transfer';
        }
        syncPaymentButtons();

        if (paymentHelp) {
            paymentHelp.textContent =
                canPayWithWallet && paymentMethod?.value === 'wallet'
                    ? 'Số dư ví đủ nên hệ thống đang ưu tiên thanh toán bằng ví. Bạn vẫn có thể chọn ATM.'
                    : canPayWithWallet
                      ? 'Bạn đang chọn thanh toán qua ngân hàng / ATM.'
                      : walletOption
                        ? 'Số dư ví chưa đủ, đơn hàng sẽ thanh toán qua ngân hàng / ATM.'
                        : 'Đăng nhập và nạp số dư để thanh toán tự động bằng ví.';
        }
    };

    const updateTotal = () => {
        const option = topupPackage?.selectedOptions[0];
        const price = Number(option?.dataset.price || 0);
        const originalPrice = Number(option?.dataset.original || price);
        const bulkResult = purchaseMode?.value === 'bulk' ? countBulkRecipients() : { quantity: 0, hasInvalidRows: false, firstInvalidLine: 0 };
        const count =
            purchaseMode?.value === 'bulk' ? (bulkResult.hasInvalidRows ? 0 : bulkResult.quantity) : Math.max(1, Number(singleQuantity?.value || 1));
        const originalTotal = originalPrice * count;
        const paymentTotal = price * count;
        const discountTotal = Math.max(0, originalTotal - paymentTotal);
        const hasPackage = Boolean(topupPackage?.value);
        const rewardLabel = option?.dataset.rewardLabel || 'Thực nhận';
        const minimum = Number(option?.dataset.min || 1);
        const maximum = Number(option?.dataset.max || 10);
        const hasServer = Boolean(server?.value);
        const canSubmit =
            hasServer &&
            hasPackage &&
            !bulkResult.hasInvalidRows &&
            (purchaseMode?.value === 'bulk' ? count > 0 : count >= minimum && count <= maximum);
        const packageLabel = option?.dataset.denomination ? formatMoney(Number(option.dataset.denomination)) : option?.dataset.name || 'Chưa chọn';
        let rewardItems = [];
        try {
            rewardItems = JSON.parse(option?.dataset.rewards || '[]');
        } catch {
            rewardItems = [];
        }
        const formatRewards = (field) => {
            const availableItems = rewardItems.filter((item) => item?.[field] !== null && item?.[field] !== undefined && item?.[field] !== '');
            if (availableItems.length === 0) return '';

            return availableItems
                .map((item) => {
                    const amount = new Intl.NumberFormat('vi-VN').format(Number(item[field]) * count);
                    const unit = availableItems.length > 1 ? item.code || item.label : item.label || rewardLabel;
                    return `${amount} ${unit}`;
                })
                .join(' | ');
        };
        const rewardDisplay = formatRewards('base_amount');
        const rewardX2Display = formatRewards('reward_x2_amount');
        const rewardX3Display = formatRewards('reward_x3_amount');

        if (total) total.textContent = formatMoney(paymentTotal);
        if (discount) discount.textContent = `-${formatMoney(discountTotal)}`;
        if (summaryPackage) summaryPackage.textContent = hasPackage ? packageLabel : 'Chưa chọn';
        if (summaryQuantityLabel) summaryQuantityLabel.textContent = purchaseMode?.value === 'bulk' ? 'Tổng số thẻ' : 'Số lượng thẻ';
        if (summaryQuantity) summaryQuantity.textContent = String(count);
        if (summaryOriginal) summaryOriginal.textContent = formatMoney(originalTotal);
        syncPaymentMethod(paymentTotal, hasPackage);

        if (summaryRewards) summaryRewards.hidden = !hasPackage;
        if (summaryReward) {
            summaryReward.textContent = rewardDisplay || 'Đang cập nhật';
        }
        if (summaryRewardX2) {
            summaryRewardX2.hidden = rewardX2Display === '';
            summaryRewardX2.textContent = `KM X2: ${rewardX2Display}`;
        }
        if (summaryRewardX3) {
            summaryRewardX3.hidden = rewardX3Display === '';
            summaryRewardX3.textContent = `KM X3: ${rewardX3Display}`;
        }

        if (submitButton) submitButton.disabled = !canSubmit;
        if (submitText) {
            submitText.textContent = !hasServer
                ? 'CHỌN MÁY CHỦ'
                : !hasPackage
                  ? 'CHỌN GÓI NẠP'
                  : bulkResult.hasInvalidRows
                    ? `KIỂM TRA DÒNG ${bulkResult.firstInvalidLine}`
                    : canSubmit
                      ? `${stepLayout ? 'THANH TOÁN' : 'NẠP NGAY'} ${formatMoney(paymentTotal)}`
                      : count === 0
                        ? 'NHẬP DANH SÁCH TÀI KHOẢN'
                        : 'KIỂM TRA SỐ LƯỢNG';
        }

        playAnimation(
            total,
            [
                { color: 'rgb(190 24 93)', transform: 'scale(1.02)' },
                { color: 'rgb(15 23 42)', transform: 'scale(1)' },
            ],
            {
                duration: 260,
            },
        );
    };

    const selectedOptionLabel = (select) => (select?.selectedOptions[0]?.textContent || '').replace(/\s*-\s*ID:\s*\d+\s*$/i, '').trim();

    const currentOrderQuantity = () => {
        if (purchaseMode?.value === 'bulk') return countBulkRecipients().quantity;

        return Math.max(1, Number(singleQuantity?.value || 1));
    };

    const closeConfirmationModal = () => {
        if (!confirmationModal || confirmationModal.hidden) return;

        confirmationModal.hidden = true;
        confirmationModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        confirmationCheckbox.checked = false;
        confirmationSubmit.disabled = true;
        confirmationPreviouslyFocused?.focus();
        confirmationPreviouslyFocused = null;
    };

    const populateConfirmationModal = () => {
        const option = topupPackage?.selectedOptions[0];
        const quantity = currentOrderQuantity();
        const paymentTotal = Number(option?.dataset.price || 0) * quantity;
        const packageLabel = option?.dataset.denomination ? formatMoney(Number(option.dataset.denomination)) : option?.dataset.name || 'Chưa chọn';

        confirmationGame.textContent = game?.selectedOptions[0]?.dataset.name || selectedOptionLabel(game) || 'Chưa chọn';
        confirmationPackage.textContent = `${packageLabel} - Tổng số lượng thẻ: ${quantity}`;
        confirmationServer.textContent = selectedOptionLabel(server) || 'Chưa chọn';
        confirmationTotal.textContent = formatMoney(paymentTotal);
        confirmationSubmitText.textContent = `NẠP NGAY ${formatMoney(paymentTotal)}`;
        confirmationRecipients.replaceChildren();

        const activeRecipientGroup = recipientFieldGroups.find((group) => !group.hidden);
        const activeRecipientInputs = Array.from(activeRecipientGroup?.querySelectorAll('[data-recipient-input]') || []).filter(
            (input) => !input.disabled,
        );
        const recipientLines =
            purchaseMode?.value === 'bulk'
                ? (bulkRecipients?.value || '')
                      .split(/\r\n|\r|\n/)
                      .map((line) => line.trim())
                      .filter(Boolean)
                : [activeRecipientInputs.map((input) => input.value.trim()).join(' | ')];

        if (confirmationRecipientLabel) {
            const recipientLabel =
                purchaseMode?.value === 'bulk'
                    ? bulkSchemas.find((schema) => !schema.hidden)?.dataset.bulkConfirmRecipientLabel || 'Tài khoản | Số lượng thẻ'
                    : activeRecipientGroup?.dataset.singleConfirmRecipientLabel || 'Tài khoản game';
            confirmationRecipientLabel.textContent = `${recipientLabel.replace(/:\s*$/, '')}:`;
        }

        recipientLines.forEach((line, index) => {
            const item = document.createElement('li');
            item.className = 'rounded-[5px] border border-slate-200 bg-white px-3 py-2';
            item.textContent = purchaseMode?.value === 'bulk' ? `${index + 1}. ${line}` : line;
            confirmationRecipients.append(item);
        });
    };

    const openConfirmationModal = () => {
        if (!confirmationModal) return;

        populateConfirmationModal();
        confirmationGranted = false;
        confirmationPreviouslyFocused = document.activeElement;
        confirmationCheckbox.checked = false;
        confirmationSubmit.disabled = true;
        confirmationModal.hidden = false;
        confirmationModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        window.setTimeout(() => confirmationCheckbox.focus(), 0);
    };

    const syncPurchaseMode = (nextMode = purchaseMode?.value === 'bulk' ? 'bulk' : 'single') => {
        const mode = nextMode === 'bulk' ? 'bulk' : 'single';
        if (purchaseMode) purchaseMode.value = mode;

        purchaseTabs.forEach((tab) => {
            const selected = tab.dataset.purchaseTab === mode;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });

        purchasePanels.forEach((panel) => {
            const selected = panel.dataset.purchasePanel === mode;
            panel.hidden = !selected;
            if (selected) {
                playAnimation(
                    panel,
                    [
                        { opacity: 0, transform: 'translateY(4px)' },
                        { opacity: 1, transform: 'translateY(0)' },
                    ],
                    {
                        duration: 180,
                    },
                );
            }
        });

        const gameId = game?.value || '';
        recipientFieldGroups.forEach((group) => {
            const active = group.dataset.recipientFields === gameId && mode === 'single';
            group.querySelectorAll('[data-recipient-input]').forEach((input) => {
                input.disabled = !active;
                input.required = active && input.dataset.required === 'true';
                validateRecipientInput(input);
            });
        });

        if (singleQuantity) {
            singleQuantity.disabled = mode !== 'single';
            singleQuantity.required = mode === 'single';
        }
        if (bulkRecipients) {
            bulkRecipients.disabled = mode !== 'bulk';
            bulkRecipients.required = mode === 'bulk';
            if (mode !== 'bulk') {
                bulkRecipients.setCustomValidity('');
                bulkRecipients.setAttribute('aria-invalid', 'false');
                if (bulkFormatError) bulkFormatError.hidden = true;
            }
        }
        serverPickers.forEach((picker) => {
            const active = picker.dataset.serverPicker === mode;
            picker.disabled = !active;
            picker.required = active;
        });

        updateTotal();
    };

    const syncRecipientSchema = () => {
        const gameId = game?.value || '';

        recipientFieldGroups.forEach((group) => {
            group.hidden = group.dataset.recipientFields !== gameId;
        });
        bulkSchemas.forEach((schema) => {
            schema.hidden = schema.dataset.bulkSchema !== gameId;
        });
        if (bulkRecipients) {
            const activeSchema = bulkSchemas.find((schema) => schema.dataset.bulkSchema === gameId);
            bulkRecipients.placeholder = activeSchema?.dataset.bulkPlaceholder || '';
        }

        syncPurchaseMode();
    };

    const filterOptions = () => {
        const gameId = game?.value || '';

        [server, ...serverPickers].forEach((picker) => {
            picker?.querySelectorAll('option[data-game]').forEach((option) => {
                option.hidden = option.dataset.game !== gameId;
                option.disabled = option.hidden;
            });
        });

        if (server?.selectedOptions[0]?.disabled) server.value = '';

        const availableServers = Array.from(server?.querySelectorAll('option[data-game]:not([disabled])') || []);
        if (server && !server.value && availableServers.length === 1) {
            server.value = availableServers[0].value;
        }

        serverPickers.forEach((picker) => {
            const matchingOption = Array.from(picker.options).find((option) => option.value === server?.value && !option.disabled);
            picker.value = matchingOption ? matchingOption.value : '';
        });
        topupPackage?.querySelectorAll('option[data-game]').forEach((option) => {
            const matchesGame = option.dataset.game === gameId;
            option.hidden = !matchesGame;
            option.disabled = option.hidden;
        });

        if (topupPackage?.selectedOptions[0]?.disabled) topupPackage.value = '';

        syncOfferPanel();
        syncGameName();
        syncPackageCards();
        syncPackageSelection();
        syncRecipientSchema();
        updateQuantityLimits();
        updateTotal();
    };

    game?.addEventListener('change', filterOptions);
    serverPickers.forEach((picker) => {
        picker.addEventListener('change', () => {
            if (server) server.value = picker.value;
            filterOptions();
        });
    });
    topupPackage?.addEventListener('change', () => {
        syncPackageSelection();
        updateQuantityLimits();
        updateTotal();
    });
    singleQuantity?.addEventListener('input', updateTotal);
    singleQuantity?.addEventListener('change', () => {
        updateQuantityLimits();
        updateTotal();
    });
    const lowercaseRecipientInput = (input) => {
        const normalizedValue = input.value.toLowerCase();
        if (normalizedValue === input.value) return;

        const selectionStart = input.selectionStart;
        const selectionEnd = input.selectionEnd;
        input.value = normalizedValue;

        if (document.activeElement === input && selectionStart !== null && selectionEnd !== null) {
            input.setSelectionRange(selectionStart, selectionEnd);
        }
    };

    recipientInputs.forEach((input) => {
        lowercaseRecipientInput(input);
        validateRecipientInput(input);
        input.addEventListener('input', (event) => {
            if (event.isComposing) return;

            lowercaseRecipientInput(input);
            validateRecipientInput(input);
        });
        input.addEventListener('compositionend', () => {
            lowercaseRecipientInput(input);
            validateRecipientInput(input);
        });
        input.addEventListener('blur', () => {
            lowercaseRecipientInput(input);
            validateRecipientInput(input);
        });
    });
    const completeBulkLineOnEnter = (event) => {
        if (!bulkRecipients || event.key !== 'Enter' || event.shiftKey || event.isComposing) return;

        const selectionStart = bulkRecipients.selectionStart ?? bulkRecipients.value.length;
        const selectionEnd = bulkRecipients.selectionEnd ?? selectionStart;
        const lineStart = bulkRecipients.value.lastIndexOf('\n', selectionStart - 1) + 1;
        const nextLineBreak = bulkRecipients.value.indexOf('\n', selectionStart);
        const lineEnd = nextLineBreak === -1 ? bulkRecipients.value.length : nextLineBreak;
        const currentLine = bulkRecipients.value.slice(lineStart, selectionStart);

        if (selectionStart !== selectionEnd || selectionStart !== lineEnd || currentLine.trim() === '' || /\|\s*\d+\s*$/.test(currentLine)) return;

        event.preventDefault();
        const quantityPrefix = /\|\s*$/.test(currentLine) ? '' : '|';
        bulkRecipients.setRangeText(`${quantityPrefix}1\n`, selectionStart, selectionEnd, 'end');
        bulkRecipients.dispatchEvent(new Event('input', { bubbles: true }));
    };

    if (bulkRecipients) lowercaseRecipientInput(bulkRecipients);
    bulkRecipients?.addEventListener('keydown', completeBulkLineOnEnter);
    bulkRecipients?.addEventListener('input', (event) => {
        if (event.isComposing) return;
        lowercaseRecipientInput(bulkRecipients);
        updateTotal();
    });
    bulkRecipients?.addEventListener('compositionend', () => {
        lowercaseRecipientInput(bulkRecipients);
        updateTotal();
    });
    paymentMethod?.addEventListener('change', () => {
        preferredPaymentMethod = paymentMethod.value;
        paymentChoiceTouched = true;
        updateTotal();
    });
    paymentButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!paymentMethod || button.disabled || !button.dataset.paymentOption) return;

            paymentMethod.value = button.dataset.paymentOption;
            paymentMethod.dispatchEvent(new Event('change', { bubbles: true }));
        });

        button.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

            event.preventDefault();
            const enabledButtons = paymentButtons.filter((paymentButton) => !paymentButton.disabled);
            const enabledIndex = enabledButtons.indexOf(button);
            let nextIndex = enabledIndex;

            if (event.key === 'ArrowLeft') nextIndex = (enabledIndex - 1 + enabledButtons.length) % enabledButtons.length;
            if (event.key === 'ArrowRight') nextIndex = (enabledIndex + 1) % enabledButtons.length;
            if (event.key === 'Home') nextIndex = 0;
            if (event.key === 'End') nextIndex = enabledButtons.length - 1;

            enabledButtons[nextIndex]?.focus();
            enabledButtons[nextIndex]?.click();
        });
    });
    purchaseTabs.forEach((tab, tabIndex) => {
        tab.addEventListener('click', () => {
            syncPurchaseMode(tab.dataset.purchaseTab);
        });

        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

            event.preventDefault();
            let nextIndex = tabIndex;

            if (event.key === 'ArrowLeft') nextIndex = (tabIndex - 1 + purchaseTabs.length) % purchaseTabs.length;
            if (event.key === 'ArrowRight') nextIndex = (tabIndex + 1) % purchaseTabs.length;
            if (event.key === 'Home') nextIndex = 0;
            if (event.key === 'End') nextIndex = purchaseTabs.length - 1;

            purchaseTabs[nextIndex]?.focus();
            purchaseTabs[nextIndex]?.click();
        });
    });

    packageButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!topupPackage || !button.dataset.packageButton) return;

            topupPackage.value = button.dataset.packageButton;
            topupPackage.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    const changeQuantity = (step) => {
        if (!singleQuantity) return;

        const minimum = Number(singleQuantity.min || 1);
        const maximum = Number(singleQuantity.max || 10);
        const current = Number(singleQuantity.value || minimum);
        singleQuantity.value = String(Math.min(maximum, Math.max(minimum, current + step)));
        singleQuantity.dispatchEvent(new Event('input', { bubbles: true }));
    };

    quantityDecrease?.addEventListener('click', () => changeQuantity(-1));
    quantityIncrease?.addEventListener('click', () => changeQuantity(1));

    rewardTabs.forEach((tab, tabIndex) => {
        tab.addEventListener('click', () => {
            if (!game || !tab.dataset.gameRewardTab) return;

            game.value = tab.dataset.gameRewardTab;
            game.dispatchEvent(new Event('change', { bubbles: true }));
        });

        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

            event.preventDefault();
            let nextIndex = tabIndex;

            if (event.key === 'ArrowLeft') nextIndex = (tabIndex - 1 + rewardTabs.length) % rewardTabs.length;
            if (event.key === 'ArrowRight') nextIndex = (tabIndex + 1) % rewardTabs.length;
            if (event.key === 'Home') nextIndex = 0;
            if (event.key === 'End') nextIndex = rewardTabs.length - 1;

            rewardTabs[nextIndex]?.focus();
            rewardTabs[nextIndex]?.click();
        });
    });

    form.querySelectorAll('[data-topup-confirmation-close]').forEach((button) => button.addEventListener('click', closeConfirmationModal));
    confirmationCheckbox?.addEventListener('change', () => {
        confirmationSubmit.disabled = !confirmationCheckbox.checked;
    });
    confirmationSubmit?.addEventListener('click', () => {
        if (!confirmationCheckbox?.checked) return;

        confirmationGranted = true;
        closeConfirmationModal();
        form.requestSubmit(submitButton);
    });
    confirmationModal?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeConfirmationModal();
    });

    form.addEventListener('submit', (event) => {
        recipientInputs.forEach(lowercaseRecipientInput);
        if (bulkRecipients) lowercaseRecipientInput(bulkRecipients);
        recipientInputs.forEach(validateRecipientInput);
        if (purchaseMode?.value === 'bulk') countBulkRecipients();

        if (!form.checkValidity()) return;

        if (!topupPackage?.value) {
            event.preventDefault();
            packageButtons.find((button) => !button.hidden)?.focus();
            void notify('error', 'Vui lòng chọn một gói nạp.');

            return;
        }

        if (confirmationModal && !confirmationGranted) {
            event.preventDefault();
            openConfirmationModal();

            return;
        }

        confirmationGranted = false;

        form.setAttribute('aria-busy', 'true');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Đang tạo đơn...';
        }

        void Swal.fire({
            title: 'Đang tạo đơn hàng',
            text: 'Hệ thống đang xác thực giá và thông tin của bạn.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            customClass: { popup: 'client-swal-popup' },
            didOpen: () => Swal.showLoading(),
        });
    });

    filterOptions();
});

document.querySelectorAll('[data-wallet-deposit]').forEach((container) => {
    const form = container.querySelector('[data-wallet-deposit-form]');
    const amountInput = container.querySelector('[data-deposit-amount-input]');
    const submitButtons = Array.from(container.querySelectorAll('[data-deposit-submit]'));
    const submitTexts = Array.from(container.querySelectorAll('[data-deposit-submit-text]'));
    const quickAmountButtons = Array.from(container.querySelectorAll('[data-deposit-amount]'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const configInputs = Array.from(container.querySelectorAll('[data-deposit-config]'));
    const summaryBank = container.querySelector('[data-deposit-summary-bank]');
    const summaryAmount = container.querySelector('[data-deposit-summary-amount]');
    const summaryTotals = Array.from(container.querySelectorAll('[data-deposit-summary-total]'));
    const summaryBonus = container.querySelector('[data-deposit-summary-bonus]');
    const summaryRate = container.querySelector('[data-deposit-summary-rate]');
    const bonusTiersElement = container.querySelector('[data-deposit-bonus-tiers]');
    let bonusTiers = [];

    try {
        bonusTiers = JSON.parse(bonusTiersElement?.textContent || '[]');
    } catch {
        bonusTiers = [];
    }

    const renderDeposit = (deposit) => {
        const paymentUrlTemplate = container.dataset.paymentUrlTemplate;

        if (!deposit?.code || !paymentUrlTemplate) return;

        window.location.assign(paymentUrlTemplate.replace('__CODE__', encodeURIComponent(deposit.code)));
    };

    const requestJson = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...options.headers,
            },
            ...options,
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok || payload.status === false) {
            const validationMessage = Object.values(payload.errors || {})
                .flat()
                .find(Boolean);
            throw new Error(
                validationMessage || (response.status === 401 ? 'Phiên đăng nhập đã hết hạn.' : 'Không thể tạo yêu cầu nạp tiền. Vui lòng thử lại.'),
            );
        }

        return payload;
    };

    quickAmountButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!amountInput) return;

            amountInput.value = button.dataset.depositAmount || '';
            amountInput.dispatchEvent(new Event('input', { bubbles: true }));
            amountInput.focus();
            animateAndRelease(amountInput, [{ transform: 'scale(0.99)' }, { transform: 'scale(1)' }], { duration: 180 });
        });
    });

    const syncDepositPreview = () => {
        const selectedConfig = configInputs.find((input) => input.checked);
        const amount = Number(amountInput?.value || 0);
        const formattedAmount = formatMoney(amount);
        const bonusTier = bonusTiers
            .filter((tier) => tier.is_active && Number(tier.minimum_amount) <= amount)
            .sort((left, right) => Number(right.minimum_amount) - Number(left.minimum_amount))[0];
        const bonusBasisPoints = Number(bonusTier?.bonus_basis_points || 0);
        const bonusPercent = bonusBasisPoints / 100;
        const bonusAmount = Math.floor((amount * bonusBasisPoints) / 10_000);

        quickAmountButtons.forEach((button) => {
            const isSelected = Number(button.dataset.depositAmount) === amount;
            button.setAttribute('aria-pressed', String(isSelected));
            button.classList.toggle('border-indigo-500', isSelected);
            button.classList.toggle('bg-indigo-50', isSelected);
            button.classList.toggle('text-indigo-700', isSelected);
        });

        if (summaryBank) summaryBank.textContent = selectedConfig?.dataset.configBank || '—';
        if (summaryAmount) summaryAmount.textContent = formattedAmount;
        if (summaryBonus) summaryBonus.textContent = `+${formatMoney(bonusAmount)}`;
        if (summaryRate) summaryRate.textContent = bonusPercent > 0 ? `(${bonusPercent}%)` : '';
        summaryTotals.forEach((summaryTotal) => {
            summaryTotal.textContent = formatMoney(amount + bonusAmount);
        });
    };

    configInputs.forEach((input) => input.addEventListener('change', syncDepositPreview));
    amountInput?.addEventListener('input', syncDepositPreview);
    syncDepositPreview();

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const amount = Number(formData.get('amount'));

        if (!Number.isInteger(amount) || amount < 10000 || amount > 50000000) {
            void Swal.fire({
                icon: 'error',
                title: 'Số tiền không hợp lệ',
                text: 'Số tiền nạp từ 10.000đ đến 50.000.000đ.',
                confirmButtonColor: '#4f46e5',
            });
            return;
        }

        submitButtons.forEach((button) => {
            button.disabled = true;
        });
        submitTexts.forEach((text) => {
            text.textContent = 'Đang tạo yêu cầu...';
        });
        void Swal.fire({
            title: 'Đang tạo yêu cầu nạp',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading(),
        });

        try {
            const payload = await requestJson(container.dataset.storeUrl, {
                method: 'POST',
                body: JSON.stringify({
                    amount,
                    config_id: formData.get('config_id') ? Number(formData.get('config_id')) : null,
                }),
            });

            Swal.close();
            renderDeposit(payload.data?.deposit_request);
            void notify('success', 'Đã tạo yêu cầu nạp.');
        } catch (error) {
            void Swal.fire({ icon: 'error', title: 'Không thể tạo yêu cầu', text: error.message, confirmButtonColor: '#059669' });
        } finally {
            submitButtons.forEach((button) => {
                button.disabled = false;
            });
            submitTexts.forEach((text) => {
                text.textContent = 'Tạo yêu cầu nạp';
            });
        }
    });
});

document.querySelectorAll('[data-wallet-payment]').forEach((container) => {
    const transactionId = Number(container.dataset.transactionId);
    const confirmButton = container.querySelector('[data-payment-confirm]');
    const confirmText = container.querySelector('[data-payment-confirm-text]');
    const statusText = container.querySelector('[data-payment-status-text]');
    const statusBadge = container.querySelector('[data-payment-status]');
    const countdown = container.querySelector('[data-payment-countdown]');
    const countdownText = container.querySelector('[data-payment-countdown-text]');
    const realtimeText = container.querySelector('[data-payment-realtime-text]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let countdownTimer = null;

    const updatePaymentStatus = (status) => {
        const labels = {
            pending: 'Đang chờ thanh toán',
            processing: 'Đang xác nhận',
            paid: 'Đã cộng tiền',
            failed: 'Thanh toán thất bại',
            cancelled: 'Đã hủy',
            expired: 'Đã hết thời gian',
        };
        const isPaid = status === 'paid';
        const isPending = ['pending', 'processing'].includes(status);

        if (statusText) statusText.textContent = labels[status] || 'Đang xử lý';
        if (statusBadge) {
            statusBadge.classList.toggle('border-emerald-300', isPaid);
            statusBadge.classList.toggle('bg-emerald-50', isPaid);
            statusBadge.classList.toggle('text-emerald-700', isPaid);
            statusBadge.classList.toggle('border-amber-300', isPending);
            statusBadge.classList.toggle('bg-amber-50', isPending);
            statusBadge.classList.toggle('text-amber-700', isPending);
        }

        if (confirmButton) confirmButton.hidden = !isPending;
        if (isPaid && realtimeText) realtimeText.textContent = 'Thanh toán thành công. Số dư ví đã được cập nhật.';
    };

    const updateCountdown = () => {
        const expiresAt = Date.parse(container.dataset.expiresAt || '');

        if (!Number.isFinite(expiresAt)) {
            if (countdown) countdown.hidden = true;
            return false;
        }

        const remainingSeconds = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
        const minutes = String(Math.floor(remainingSeconds / 60)).padStart(2, '0');
        const seconds = String(remainingSeconds % 60).padStart(2, '0');

        if (countdownText) countdownText.textContent = `${minutes}:${seconds}`;

        if (remainingSeconds === 0) {
            if (!['paid', 'failed', 'cancelled'].includes(container.dataset.currentStatus)) updatePaymentStatus('expired');

            return false;
        }

        return true;
    };

    const scheduleCountdown = () => {
        if (!updateCountdown()) return;

        countdownTimer = window.setTimeout(scheduleCountdown, 1000);
    };

    const confirmPayment = async () => {
        if (!confirmButton || !container.dataset.confirmUrl) return;

        confirmButton.disabled = true;
        if (confirmText) confirmText.textContent = 'Đang kiểm tra...';

        try {
            const response = await fetch(container.dataset.confirmUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: '{}',
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok || payload.status === false) throw new Error('Chưa thể kiểm tra giao dịch. Vui lòng thử lại.');

            const status = payload.data?.deposit_request?.status || 'processing';
            container.dataset.currentStatus = status;
            updatePaymentStatus(status);
            void notify('success', status === 'paid' ? 'Tiền đã được cộng vào ví.' : 'Giao dịch đang được xác nhận.');
        } catch (error) {
            void Swal.fire({ icon: 'error', title: 'Không thể kiểm tra giao dịch', text: error.message, confirmButtonColor: '#4f46e5' });
        } finally {
            confirmButton.disabled = false;
            if (confirmText) confirmText.textContent = 'Tôi đã chuyển khoản';
        }
    };

    confirmButton?.addEventListener('click', confirmPayment);
    updatePaymentStatus(container.dataset.currentStatus || 'pending');
    scheduleCountdown();

    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
    const walletChannel = container.dataset.walletChannel;

    if (reverbKey && walletChannel) {
        window.Pusher = Pusher;
        const echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });

        echo.private(walletChannel).listen('.wallet.deposit.credited', (event) => {
            if (Number(event.payment_transaction_id) !== transactionId) return;

            container.dataset.currentStatus = 'paid';
            updatePaymentStatus('paid');
            if (countdownTimer) window.clearTimeout(countdownTimer);
            void Swal.fire({
                icon: 'success',
                title: 'Nạp tiền thành công',
                text: 'Số dư ví của bạn đã được cập nhật.',
                confirmButtonColor: '#4f46e5',
            });
        });

        window.addEventListener('pagehide', () => {
            if (countdownTimer) window.clearTimeout(countdownTimer);
            echo.leave(walletChannel);
            echo.disconnect();
        });
    } else {
        window.addEventListener('pagehide', () => {
            if (countdownTimer) window.clearTimeout(countdownTimer);
        });
    }
});

const realtimeOrderContainers = Array.from(document.querySelectorAll('[data-order-realtime-channel]'));

if (realtimeOrderContainers.length > 0) {
    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

    const updateRealtimeConnection = (state, message) => {
        realtimeOrderContainers.forEach((container) => {
            const indicator = container.querySelector('[data-order-realtime-connection]');
            const text = container.querySelector('[data-order-realtime-text]');
            const dot = container.querySelector('[data-order-realtime-dot]');

            if (indicator) indicator.dataset.realtimeState = state;
            if (text) text.textContent = message;
            if (dot) {
                dot.classList.remove('bg-amber-400', 'bg-emerald-500', 'bg-rose-500', 'animate-pulse');
                const dotColor = ['connected', 'updated'].includes(state)
                    ? 'bg-emerald-500'
                    : state === 'connecting'
                      ? 'bg-amber-400'
                      : 'bg-rose-500';

                dot.classList.add(dotColor);
                if (state === 'connecting') dot.classList.add('animate-pulse');
            }
        });
    };

    if (!reverbKey) {
        updateRealtimeConnection('unavailable', 'Realtime chưa được cấu hình. Vui lòng tải lại trang để kiểm tra.');
    } else {
        window.Pusher = Pusher;

        const echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
        const channelNames = new Set(realtimeOrderContainers.map((container) => container.dataset.orderRealtimeChannel).filter(Boolean));
        let refreshScheduled = false;
        const paymentStatusLabels = {
            pending: 'Chờ thanh toán',
            paid: 'Đã thanh toán',
            expired: 'Hết hạn thanh toán',
            cancelled: 'Đã hủy thanh toán',
            refunded: 'Đã hoàn tiền',
        };
        const orderStatusLabels = {
            pending: 'Chờ xử lý',
            processing: 'Đang xử lý',
            completed: 'Hoàn thành',
            failed: 'Xử lý thất bại',
            cancelled: 'Đã hủy',
        };

        const updateOrderRealtimeState = (container, status) => {
            const previousPaymentStatus = container.dataset.orderPaymentStatus;
            const previousOrderStatus = container.dataset.orderStatus;
            const paymentStatus = String(status.payment_status ?? previousPaymentStatus ?? 'pending');
            const orderStatus = String(status.order_status ?? previousOrderStatus ?? 'pending');
            const recipientSummary = status.recipient_summary;

            container.dataset.orderPaymentStatus = paymentStatus;
            container.dataset.orderStatus = orderStatus;
            container.querySelectorAll('[data-order-payment-status-text], [data-payment-status-text]').forEach((element) => {
                element.textContent = paymentStatusLabels[paymentStatus] ?? 'Đang xử lý';
            });
            container.querySelectorAll('[data-order-status-text]').forEach((element) => {
                element.textContent = orderStatusLabels[orderStatus] ?? 'Đang xử lý';
            });

            if (recipientSummary && typeof recipientSummary === 'object') {
                const summary = container.querySelector('[data-order-recipient-summary]');
                if (summary) {
                    const completed = Number(recipientSummary.completed ?? 0);
                    const failed = Number(recipientSummary.failed ?? 0);
                    const total = Number(recipientSummary.total ?? 0);
                    summary.textContent =
                        failed > 0 ? `${completed}/${total} hoàn tất · ${failed} thất bại` : `${completed}/${total} tài khoản hoàn tất`;
                }
            }

            return {
                highLevelChanged: previousPaymentStatus !== paymentStatus || previousOrderStatus !== orderStatus,
                orderStatus,
                paymentStatus,
            };
        };

        updateRealtimeConnection('connecting', 'Đang kết nối cập nhật realtime...');

        channelNames.forEach((channelName) => {
            echo.channel(channelName).listen('.order.status.updated', (status) => {
                const matchingContainers = realtimeOrderContainers.filter((container) => container.dataset.orderRealtimeChannel === channelName);
                let shouldRefresh = false;
                let shouldOpenOrder = false;

                matchingContainers.forEach((container) => {
                    const nextState = updateOrderRealtimeState(container, status);
                    shouldRefresh ||= nextState.highLevelChanged;
                    shouldOpenOrder ||= container.dataset.orderPage === 'payment' && nextState.paymentStatus === 'paid';
                });

                updateRealtimeConnection('updated', 'Đã nhận trạng thái mới qua realtime.');

                if (!shouldRefresh || refreshScheduled) return;

                refreshScheduled = true;
                void notify(
                    status.order_status === 'failed' ? 'error' : 'success',
                    status.order_status === 'completed'
                        ? 'Đơn hàng đã hoàn thành'
                        : status.order_status === 'failed'
                          ? 'Đơn hàng cần được kiểm tra'
                          : status.payment_status === 'paid'
                            ? 'Thanh toán đã được xác nhận'
                            : 'Trạng thái đơn vừa được cập nhật',
                );

                window.setTimeout(() => {
                    const orderUrl = matchingContainers.find((container) => container.dataset.orderShowUrl)?.dataset.orderShowUrl;
                    if (shouldOpenOrder && orderUrl) {
                        window.location.assign(orderUrl);

                        return;
                    }

                    window.location.reload();
                }, 650);
            });
        });

        const connection = echo.connector.pusher.connection;
        connection.bind('connected', () => updateRealtimeConnection('connected', 'Đang nhận cập nhật realtime.'));
        connection.bind('disconnected', () => updateRealtimeConnection('disconnected', 'Mất kết nối realtime. Hệ thống đang kết nối lại...'));
        connection.bind('error', () => updateRealtimeConnection('error', 'Không thể kết nối realtime. Vui lòng kiểm tra lại kết nối mạng.'));

        window.addEventListener('pagehide', () => {
            channelNames.forEach((channelName) => echo.leave(channelName));
            echo.disconnect();
        });
    }
}
