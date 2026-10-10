import assert from 'node:assert/strict';
import test from 'node:test';
import {
    checkoutCanPay,
    checkoutPaymentLabel,
    servicePayloadValues,
    subscriptionPayload,
    updateCheckoutTotals,
} from '../../resources/js/client-subscriptions.js';

test('visible payment button explains the missing step and shows payable total when ready', () => {
    assert.equal(checkoutPaymentLabel(false, false, 100000, 50000), 'Chọn gói dịch vụ');
    assert.equal(checkoutPaymentLabel(true, false, 100000, 50000), 'Hoàn tất thông tin bước 2');
    assert.equal(checkoutPaymentLabel(true, true, 0, 50000), 'Số dư chưa đủ để thanh toán');
    assert.equal(checkoutPaymentLabel(true, true, 100000, 50000), 'Thanh toán 50.000 đ & kích hoạt');
    assert.equal(checkoutPaymentLabel(true, true, 0, 0), 'Thanh toán 0 đ & kích hoạt');
    assert.equal(checkoutPaymentLabel(true, true, 100000, 50000, true), 'Đang xử lý thanh toán…');
});

test('payment step keeps package price total and remaining balance consistent when choosing packages', () => {
    const nodes = Object.fromEntries(
        ['[data-checkout-total-heading]', '[data-review-unit-price]', '[data-review-price]', '[data-review-balance-after]'].map((key) => [
            key,
            { textContent: '' },
        ]),
    );
    const form = { querySelector: (key) => nodes[key] };
    updateCheckoutTotals(form, 50000, 100000, true);
    assert.equal(nodes['[data-review-unit-price]'].textContent, '50.000 đ');
    assert.equal(nodes['[data-review-price]'].textContent, '50.000 đ');
    assert.equal(nodes['[data-checkout-total-heading]'].textContent, '50.000 đ');
    assert.equal(nodes['[data-review-balance-after]'].textContent, '50.000 đ');
    updateCheckoutTotals(form, 150000, 100000, true);
    assert.equal(nodes['[data-review-price]'].textContent, '150.000 đ');
    assert.equal(nodes['[data-review-balance-after]'].textContent, 'Chưa đủ số dư');
    updateCheckoutTotals(form, 0, 0, true);
    assert.equal(nodes['[data-review-price]'].textContent, '0 đ');
    updateCheckoutTotals(form, NaN, 0, false);
    assert.equal(nodes['[data-review-price]'].textContent, '');
});

test('checkout requires package selection reviewed data and sufficient balance including free packages', () => {
    assert.equal(checkoutCanPay(false, true, 100, 50), false);
    assert.equal(checkoutCanPay(true, false, 100, 50), false);
    assert.equal(checkoutCanPay(true, true, 49, 50), false);
    assert.equal(checkoutCanPay(true, true, 50, 50), true);
    assert.equal(checkoutCanPay(true, true, 0, 0), true);
    assert.equal(checkoutCanPay(true, true, 100, NaN), false);
});

test('service payload includes active schema fields with typed values and ignores other services', () => {
    const input = (name, type, value, extra = {}) => ({ dataset: { serviceField: name }, type, value, disabled: false, ...extra });
    assert.deepEqual(
        servicePayloadValues([
            input('email', 'email', 'a@example.com'),
            input('count', 'number', '12.5'),
            input('enabled', 'checkbox', '', { checked: false }),
            input('notes', 'text', ''),
            input('other', 'password', 'do-not-send', { disabled: true }),
        ]),
        { email: 'a@example.com', count: 12.5, enabled: false, notes: null },
    );
});
test('personal payload includes one Zalo and names with server codes', () => {
    const row = { querySelector: (selector) => ({ value: selector === '[data-char-name]' ? ' Char A ' : '2' }) };
    const form = {
        dataset: { requestId: 'request-id' },
        querySelector: (selector) => (selector === '[data-package]' ? { value: '3' } : { value: ' 001zalo ' }),
        querySelectorAll: (selector) => (selector === '[data-character]' ? [row] : [{ value: 'BOSS' }, { value: 'SET_ACTIVATION' }]),
    };
    assert.deepEqual(subscriptionPayload(form, 'personal'), {
        package_id: 3,
        request_id: 'request-id',
        zalo_id: '001zalo',
        notification_types: ['BOSS', 'SET_ACTIVATION'],
        characters: [{ char_name: 'Char A', char_server: 2 }],
    });
});
test('webhook update excludes personal and payment fields', () => {
    const form = { querySelector: (selector) => (selector === '[data-package]' ? null : { value: ' https://example.com/webhook ' }) };
    assert.deepEqual(subscriptionPayload(form, 'webhook'), { webhook_url: 'https://example.com/webhook' });
});

test('generic service checkout sends only the services configured payload without Zalo or webhook requirements', () => {
    const form = {
        dataset: { requestId: 'request-id' },
        hasAttribute: (name) => name === 'data-checkout-wizard',
        querySelector: () => ({ value: '12' }),
        querySelectorAll: () => [{ dataset: { serviceField: 'character' }, type: 'text', value: 'Player 1', disabled: false }],
    };
    assert.deepEqual(subscriptionPayload(form, 'service'), { package_id: 12, request_id: 'request-id', service_payload: { character: 'Player 1' } });
});
