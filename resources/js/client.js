import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const formatMoney = (value) => `${new Intl.NumberFormat('vi-VN').format(Number(value || 0))}đ`;
const guestOrderHistoryKey = 'napcarot.guest-order-history.v1';
const guestOrderLifetime = 365 * 24 * 60 * 60 * 1000;
const guestOrderLimit = 30;

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

const initializeGuestOrderHistory = () => {
    if (document.body.dataset.authenticated === 'true') {
        removeGuestOrderHistory();
        return;
    }

    let orders = readGuestOrderHistory();
    const orderMarker = document.querySelector('[data-guest-order-code]');
    const code = orderMarker?.dataset.guestOrderCode?.trim().toUpperCase() || '';

    if (/^TOP[A-Z0-9]{6,32}$/.test(code)) {
        const existing = orders.find((entry) => entry.code === code);
        const createdAtValue = orderMarker?.dataset.guestOrderCreatedAt || '';
        const createdAt = Number.isFinite(Date.parse(createdAtValue)) ? createdAtValue : new Date().toISOString();
        const entry = existing || { code, createdAt, expiresAt: Date.now() + guestOrderLifetime };
        orders = [entry, ...orders.filter((item) => item.code !== code)];
    }

    writeGuestOrderHistory(orders);

    const history = document.querySelector('[data-guest-order-history]');
    const list = history?.querySelector('[data-guest-order-history-list]');
    const template = history?.querySelector('[data-guest-order-history-item]');
    const lookupUrl = document.body.dataset.orderLookupUrl;

    if (!history || !list || !(template instanceof HTMLTemplateElement) || !lookupUrl || orders.length === 0) return;

    orders.forEach((entry) => {
        const item = template.content.cloneNode(true);
        const orderCode = item.querySelector('[data-guest-order-history-code]');
        const orderTime = item.querySelector('[data-guest-order-history-time]');
        const orderLink = item.querySelector('[data-guest-order-history-link]');
        const url = new URL(lookupUrl, window.location.origin);
        url.searchParams.set('code', entry.code);

        if (orderCode) orderCode.textContent = entry.code;
        if (orderTime) {
            orderTime.dateTime = entry.createdAt;
            orderTime.textContent = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(entry.createdAt));
        }
        if (orderLink) orderLink.href = url.toString();
        list.append(item);
    });

    history.hidden = false;
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

document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        const original = button.textContent;

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
                button.textContent = original;
                button.disabled = false;
            }, 1400);
        }
    });
});

document.querySelectorAll('[data-topup-form]').forEach((form) => {
    const game = form.querySelector('[name="game_id"]');
    const server = form.querySelector('[name="server_id"]');
    const serverPickers = Array.from(form.querySelectorAll('[data-server-picker]'));
    const topupPackage = form.querySelector('[name="package_id"]');
    const purchaseMode = form.querySelector('[data-purchase-mode]');
    const purchaseTabs = Array.from(form.querySelectorAll('[data-purchase-tab]'));
    const purchasePanels = Array.from(form.querySelectorAll('[data-purchase-panel]'));
    const recipientFieldGroups = Array.from(form.querySelectorAll('[data-recipient-fields]'));
    const bulkSchemas = Array.from(form.querySelectorAll('[data-bulk-schema]'));
    const bulkRecipients = form.querySelector('[data-bulk-recipients]');
    const bulkAccountCount = form.querySelector('[data-bulk-account-count]');
    const bulkCount = form.querySelector('[data-bulk-count]');
    const singleQuantity = form.querySelector('[data-single-quantity]');
    const quantityDecrease = form.querySelector('[data-quantity-decrease]');
    const quantityIncrease = form.querySelector('[data-quantity-increase]');
    const packageGroups = Array.from(form.querySelectorAll('[data-package-options]'));
    const packageButtons = Array.from(form.querySelectorAll('[data-package-button]'));
    const total = form.querySelector('[data-order-total]');
    const discount = form.querySelector('[data-order-discount]');
    const summaryPackage = form.querySelector('[data-summary-package]');
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
    const paymentHelp = form.querySelector('[data-payment-help]');
    let preferredPaymentMethod = paymentMethod?.value || 'bank_transfer';
    let paymentChoiceTouched = paymentMethod?.dataset.paymentExplicit === 'true';
    const offerPanels = document.querySelectorAll('[data-game-offer]');
    const rewardPanels = document.querySelectorAll('[data-game-reward]');
    const rewardTabs = Array.from(document.querySelectorAll('[data-game-reward-tab]'));

    const syncGameName = () => {
        const gameName = game?.selectedOptions[0]?.textContent?.trim() || 'Chọn game';

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
    };

    const syncPackageCards = () => {
        const gameId = game?.value || '';
        const serverId = server?.value || '';

        packageGroups.forEach((group) => {
            group.hidden = group.dataset.packageOptions !== gameId;
        });

        packageButtons.forEach((button) => {
            const group = button.closest('[data-package-options]');
            const matchesGame = group?.dataset.packageOptions === gameId;
            const matchesServer = !serverId || !button.dataset.packageServer || button.dataset.packageServer === serverId;
            button.hidden = !matchesGame || !matchesServer;
        });
    };

    const updateQuantityLimits = () => {
        const option = topupPackage?.selectedOptions[0];
        const minimum = Number(option?.dataset.min || 1);
        const maximum = Number(option?.dataset.max || 100);

        if (singleQuantity) {
            singleQuantity.min = String(minimum);
            singleQuantity.max = String(maximum);
            singleQuantity.value = String(Math.min(maximum, Math.max(minimum, Number(singleQuantity.value || minimum))));
        }
    };

    const countBulkRecipients = () => {
        const lines = (bulkRecipients?.value || '').split(/\r\n|\r|\n/).filter((line) => line.trim() !== '');
        const quantity = lines.reduce((totalQuantity, line) => {
            const values = line.split('|');
            const value = values[values.length - 1]?.trim() || '';

            return /^[1-9]\d*$/.test(value) ? totalQuantity + Number(value) : totalQuantity;
        }, 0);

        if (bulkAccountCount) bulkAccountCount.textContent = String(lines.length);
        if (bulkCount) bulkCount.textContent = String(quantity);

        return quantity;
    };

    const syncPaymentMethod = (paymentTotal, hasPackage) => {
        const walletBalance = Number(walletOption?.dataset.walletBalance || 0);
        const canPayWithWallet = Boolean(walletOption) && hasPackage && walletBalance >= paymentTotal;

        if (walletOption) walletOption.disabled = !canPayWithWallet;
        if (paymentMethod) {
            paymentMethod.value = canPayWithWallet && (!paymentChoiceTouched || preferredPaymentMethod === 'wallet') ? 'wallet' : 'bank_transfer';
        }

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
        const count = purchaseMode?.value === 'bulk' ? countBulkRecipients() : Math.max(1, Number(singleQuantity?.value || 1));
        const originalTotal = originalPrice * count;
        const paymentTotal = price * count;
        const discountTotal = Math.max(0, originalTotal - paymentTotal);
        const hasPackage = Boolean(topupPackage?.value);
        const rewardAmount = Number(option?.dataset.reward || 0) * count;
        const rewardX2Amount = Number(option?.dataset.rewardX2 || 0) * count;
        const rewardX3Amount = Number(option?.dataset.rewardX3 || 0) * count;
        const rewardLabel = option?.dataset.rewardLabel || 'Thực nhận';
        const minimum = Number(option?.dataset.min || 1);
        const maximum = Number(option?.dataset.max || 100);
        const hasServer = Boolean(server?.value);
        const canSubmit = hasServer && hasPackage && count >= minimum && count <= maximum;
        const packageLabel = option?.dataset.denomination ? formatMoney(Number(option.dataset.denomination)) : option?.dataset.name || 'Chưa chọn';

        if (total) total.textContent = formatMoney(paymentTotal);
        if (discount) discount.textContent = `-${formatMoney(discountTotal)}`;
        if (summaryPackage) summaryPackage.textContent = hasPackage ? packageLabel : 'Chưa chọn';
        if (summaryQuantity) summaryQuantity.textContent = String(count);
        if (summaryOriginal) summaryOriginal.textContent = formatMoney(originalTotal);
        syncPaymentMethod(paymentTotal, hasPackage);

        if (summaryRewards) summaryRewards.hidden = !hasPackage;
        if (summaryReward) {
            summaryReward.textContent = rewardAmount > 0 ? `${new Intl.NumberFormat('vi-VN').format(rewardAmount)} ${rewardLabel}` : 'Đang cập nhật';
        }
        if (summaryRewardX2) {
            summaryRewardX2.hidden = rewardX2Amount <= 0;
            summaryRewardX2.textContent = `KM X2: ${new Intl.NumberFormat('vi-VN').format(rewardX2Amount)}`;
        }
        if (summaryRewardX3) {
            summaryRewardX3.hidden = rewardX3Amount <= 0;
            summaryRewardX3.textContent = `KM X3: ${new Intl.NumberFormat('vi-VN').format(rewardX3Amount)}`;
        }

        if (submitButton) submitButton.disabled = !canSubmit;
        if (submitText) {
            submitText.textContent = !hasServer
                ? 'CHỌN MÁY CHỦ'
                : !hasPackage
                  ? 'CHỌN GÓI NẠP'
                  : canSubmit
                    ? `NẠP NGAY ${formatMoney(paymentTotal)}`
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
            });
        });

        if (singleQuantity) {
            singleQuantity.disabled = mode !== 'single';
            singleQuantity.required = mode === 'single';
        }
        if (bulkRecipients) {
            bulkRecipients.disabled = mode !== 'bulk';
            bulkRecipients.required = mode === 'bulk';
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
        const serverId = server?.value || '';

        topupPackage?.querySelectorAll('option[data-game]').forEach((option) => {
            const matchesGame = option.dataset.game === gameId;
            const matchesServer = !serverId || !option.dataset.server || option.dataset.server === serverId;
            option.hidden = !matchesGame || !matchesServer;
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
    bulkRecipients?.addEventListener('input', updateTotal);
    paymentMethod?.addEventListener('change', () => {
        preferredPaymentMethod = paymentMethod.value;
        paymentChoiceTouched = true;
        updateTotal();
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
        const maximum = Number(singleQuantity.max || 100);
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

    form.addEventListener('submit', (event) => {
        if (!form.checkValidity()) return;

        if (!topupPackage?.value) {
            event.preventDefault();
            packageButtons.find((button) => !button.hidden)?.focus();
            void notify('error', 'Vui lòng chọn một gói nạp.');

            return;
        }

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

        quickAmountButtons.forEach((button) => {
            const isSelected = Number(button.dataset.depositAmount) === amount;
            button.setAttribute('aria-pressed', String(isSelected));
            button.classList.toggle('border-indigo-500', isSelected);
            button.classList.toggle('bg-indigo-50', isSelected);
            button.classList.toggle('text-indigo-700', isSelected);
        });

        if (summaryBank) summaryBank.textContent = selectedConfig?.dataset.configBank || '—';
        if (summaryAmount) summaryAmount.textContent = formattedAmount;
        summaryTotals.forEach((summaryTotal) => {
            summaryTotal.textContent = formattedAmount;
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
