import axios from 'axios';
import Swal from 'sweetalert2';

export function servicePayloadValues(inputs) {
    return Object.fromEntries(
        [...inputs]
            .filter((input) => !input.disabled)
            .map((input) => [
                input.dataset.serviceField,
                input.type === 'checkbox' ? input.checked : input.value === '' ? null : input.type === 'number' ? Number(input.value) : input.value,
            ]),
    );
}

export function checkoutCanPay(packageSelected, reviewed, balance, price) {
    return Boolean(packageSelected && reviewed && Number.isFinite(price) && price >= 0 && balance >= price);
}

export function checkoutPaymentLabel(packageSelected, reviewed, balance, price, pending = false) {
    if (pending) return 'Đang xử lý thanh toán…';
    if (!packageSelected) return 'Chọn gói dịch vụ';
    if (!reviewed) return 'Hoàn tất thông tin bước 2';
    if (balance < price) return 'Số dư chưa đủ để thanh toán';
    return `Thanh toán ${price.toLocaleString('vi-VN')} đ & kích hoạt`;
}

export function updateCheckoutTotals(form, price, balance, selected) {
    const valid = selected && Number.isFinite(price) && price >= 0;
    const amount = valid ? `${price.toLocaleString('vi-VN')} đ` : '';
    for (const selector of ['[data-checkout-total-heading]', '[data-review-unit-price]', '[data-review-price]']) {
        form.querySelector(selector).textContent = amount;
    }
    form.querySelector('[data-review-balance-after]').textContent = valid
        ? balance >= price
            ? `${(balance - price).toLocaleString('vi-VN')} đ`
            : 'Chưa đủ số dư'
        : '';
}

export function subscriptionPayload(form, mode) {
    const payload =
        mode === 'personal'
            ? {
                  zalo_id: form.querySelector('[name="zalo_id"]').value.trim(),
                  notification_types: [...form.querySelectorAll('[name="notification_types"]:checked')].map((input) => input.value),
                  characters: [...form.querySelectorAll('[data-character]')].map((row) => ({
                      char_name: row.querySelector('[data-char-name]').value.trim(),
                      char_server: Number(row.querySelector('[data-char-server]').value),
                  })),
              }
            : mode === 'webhook'
              ? { webhook_url: form.querySelector('[name="webhook_url"]').value.trim() }
              : {};
    const selectedPackage = form.querySelector('[data-package]');
    if (selectedPackage) {
        payload.package_id = Number(selectedPackage.value);
        payload.request_id = form.dataset.requestId;
    }
    if (form.hasAttribute?.('data-checkout-wizard')) {
        payload.service_payload = servicePayloadValues(form.querySelectorAll('[data-service-field]'));
    }
    return payload;
}

export function initializeNotificationSubscriptions(root = document, http = axios, alerts = Swal) {
    root.querySelectorAll('[data-notification-subscription]').forEach((form) => {
        if (form.dataset.initialized) return;
        form.dataset.initialized = 'true';
        form.dataset.requestId = crypto.randomUUID();
        const selectedPackage = form.querySelector('[data-package]');
        const wizard = form.hasAttribute('data-checkout-wizard');
        const characters = form.querySelector('[data-characters]');
        const template = characters.firstElementChild.cloneNode(true);
        const submit = form.querySelector('[type="submit"]');
        let reviewed = false;
        let pending = false;
        const moveTo = (element) => {
            element.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        };
        const refresh = (resetReview = true) => {
            if (resetReview) reviewed = false;
            const option = selectedPackage?.selectedOptions[0];
            const hasPackage = selectedPackage ? Boolean(selectedPackage.value) : true;
            form.dataset.mode = selectedPackage ? option?.dataset.mode || '' : form.dataset.mode;
            const personal = form.dataset.mode === 'personal';
            if (selectedPackage) {
                form.querySelectorAll('[data-select-package]').forEach((button) => {
                    const selected = button.dataset.selectPackage === selectedPackage.value;
                    button.setAttribute('aria-pressed', String(selected));
                    button.querySelector('[data-package-selection-label]').textContent = selected ? 'Đang chọn' : 'Chọn gói này';
                    const card = button.closest('[data-package-card]');
                    card.classList.toggle('ring-2', selected);
                    card.classList.toggle('ring-emerald-600', selected);
                });
                form.querySelectorAll('[data-service-payload]').forEach((group) => {
                    const active = hasPackage && group.dataset.servicePayload === option?.dataset.serviceCode;
                    group.hidden = !active;
                    group.querySelectorAll('[data-service-field]').forEach((input) => {
                        input.disabled = !active;
                        input.required = active && input.type !== 'checkbox' && input.dataset.required === 'true';
                    });
                });
            }
            for (const [selector, visible] of [
                ['[data-personal-fields]', hasPackage && personal],
                ['[data-webhook-fields]', hasPackage && form.dataset.mode === 'webhook'],
            ]) {
                const group = form.querySelector(selector);
                group.hidden = !visible;
                group.querySelectorAll('input,select,button').forEach((input) => {
                    input.disabled = !visible;
                    if (input.matches('[name="zalo_id"],[name="webhook_url"],[data-char-name],[data-char-server]')) input.required = visible;
                });
            }
            form.querySelector('[data-character-count]').textContent = characters.childElementCount;
            form.querySelector('[data-add-character]').disabled = !hasPackage || !personal || characters.childElementCount >= 10;
            characters.querySelectorAll('[data-remove-character]').forEach((button) => {
                button.disabled = !hasPackage || !personal || characters.childElementCount <= 1;
            });
            if (wizard) {
                form.querySelector('[data-payload-locked]').hidden = hasPackage;
                form.querySelector('[data-checkout-payload]').hidden = !hasPackage;
                form.querySelector('[data-payment-locked]').hidden = reviewed;
                form.querySelector('[data-checkout-payment]').hidden = false;
                form.querySelector('[data-payment-summary]').hidden = !hasPackage;
                const price = Number(option?.dataset.price);
                const balance = Number(form.dataset.walletBalance);
                form.querySelector('[data-review-name]').textContent = option?.dataset.name || '';
                form.querySelector('[data-review-entitlement]').textContent = option?.dataset.entitlement || '';
                updateCheckoutTotals(form, price, balance, hasPackage);
                form.querySelector('[data-wallet-insufficient]').hidden = !hasPackage || balance >= price;
                submit.disabled = pending || !checkoutCanPay(hasPackage, reviewed, balance, price);
                form.querySelector('[data-payment-submit-label]').textContent = checkoutPaymentLabel(hasPackage, reviewed, balance, price, pending);
            }
        };
        selectedPackage?.addEventListener('change', () => refresh());
        if (selectedPackage) {
            form.querySelectorAll('[data-select-package]').forEach((button) => {
                button.addEventListener('click', () => {
                    if (pending) return;
                    selectedPackage.value = button.dataset.selectPackage;
                    refresh();
                    moveTo(form.querySelector('[data-checkout-step="2"]'));
                    form.querySelector('[data-checkout-payload] input:not(:disabled)')?.focus({ preventScroll: true });
                });
            });
        }
        form.querySelector('[data-add-character]').addEventListener('click', () => {
            if (pending || characters.childElementCount >= 10) return;
            const row = template.cloneNode(true);
            row.querySelector('[data-char-name]').value = '';
            characters.append(row);
            refresh();
        });
        characters.addEventListener('click', (event) => {
            if (!pending && event.target.closest('[data-remove-character]') && characters.childElementCount > 1) {
                event.target.closest('[data-character]').remove();
                refresh();
            }
        });
        const validPayload = () => {
            if (!form.reportValidity()) return false;
            if (form.dataset.mode === 'personal' && !form.querySelector('[name="notification_types"]:checked')) {
                void alerts.fire({
                    icon: 'info',
                    titleText: 'Chọn loại thông báo',
                    text: 'Vui lòng chọn ít nhất một loại thông báo.',
                    heightAuto: false,
                });
                return false;
            }
            return true;
        };
        if (wizard) {
            for (const name of ['input', 'change'])
                form.addEventListener(name, (event) => {
                    if (!pending && event.target.closest('[data-checkout-payload]')) refresh();
                });
            form.querySelector('[data-review-checkout]').addEventListener('click', () => {
                if (pending || !selectedPackage.value || !validPayload()) return;
                reviewed = true;
                refresh(false);
                moveTo(form.querySelector('[data-checkout-step="3"]'));
            });
            form.querySelector('[data-edit-payload]').addEventListener('click', () => {
                if (pending) return;
                refresh();
                moveTo(form.querySelector('[data-checkout-step="2"]'));
            });
        }
        refresh();
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (pending) return;
            if (
                wizard &&
                !checkoutCanPay(
                    Boolean(selectedPackage.value),
                    reviewed,
                    Number(form.dataset.walletBalance),
                    Number(selectedPackage.selectedOptions[0]?.dataset.price),
                )
            )
                return;
            if (!validPayload()) return;
            const payload = subscriptionPayload(form, form.dataset.mode);
            pending = true;
            submit.disabled = true;
            if (wizard) {
                form.querySelector('[data-payment-submit-label]').textContent = checkoutPaymentLabel(true, reviewed, 0, 0, true);
            }
            const controls = [...form.querySelectorAll('input,select,textarea,button')].filter((input) => !input.disabled);
            controls.forEach((input) => {
                input.disabled = true;
            });
            form.setAttribute('aria-busy', 'true');
            try {
                if (selectedPackage) {
                    const option = selectedPackage.selectedOptions[0];
                    const confirmation = await alerts.fire({
                        titleText: 'Xác nhận thanh toán',
                        text: `Gói ${option.dataset.name}: trừ ${Number(option.dataset.price).toLocaleString('vi-VN')} đ từ ví và kích hoạt ngay.${form.dataset.mode === 'personal' ? ' Zalo chưa kết nối API gửi tin.' : ''}`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Thanh toán',
                        cancelButtonText: 'Huỷ',
                        heightAuto: false,
                    });
                    if (!confirmation.isConfirmed) return;
                }
                const { data } = await http.request({
                    url: form.action,
                    method: form.dataset.method || 'post',
                    data: payload,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                    },
                });
                await alerts.fire({ icon: 'success', titleText: 'Thành công', text: data.message, heightAuto: false });
                window.location.reload();
            } catch (error) {
                const data = error.response?.data;
                const text = data?.errors ? Object.values(data.errors).flat().join('\n') : data?.message || 'Không thể lưu. Vui lòng thử lại.';
                await alerts.fire({ icon: 'error', titleText: 'Không thể thanh toán', text, heightAuto: false });
                if (wizard && data?.errors) reviewed = false;
            } finally {
                pending = false;
                controls.forEach((input) => {
                    input.disabled = false;
                });
                submit.disabled = false;
                refresh(false);
                form.removeAttribute('aria-busy');
            }
        });
    });
}
