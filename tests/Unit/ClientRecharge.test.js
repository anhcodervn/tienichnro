import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeClientRecharge, initializeRechargeForms } from '../../resources/js/client-recharge.js';

function fixture(next = 'pending') {
    const listeners = {};
    let scheduled;
    let refresh;
    let requests = 0;
    let reloads = 0;
    const message = {};
    const section = {
        querySelectorAll: () => [],
        dataset: { status: 'pending', statusUrl: '/tai-khoan/nap-tien/1/trang-thai' },
        querySelector: (selector) =>
            selector.includes('live-status')
                ? message
                : selector.includes('refresh')
                  ? {
                        addEventListener: (_name, fn) => {
                            refresh = fn;
                        },
                    }
                  : null,
    };
    const root = {
        hidden: false,
        querySelectorAll: (selector) => (selector === '[data-wallet-recharge]' ? [section] : []),
        addEventListener: (name, fn) => {
            listeners[name] = fn;
        },
    };
    const environment = {
        setTimeout: (fn) => {
            scheduled = fn;
        },
        clearTimeout: () => {
            scheduled = null;
        },
        addEventListener: (name, fn) => {
            listeners[name] = fn;
        },
        location: {
            reload: () => {
                reloads++;
            },
        },
    };
    const request = async (url) => {
        requests++;
        assert.equal(url, section.dataset.statusUrl);
        return { ok: true, status: 200, json: async () => ({ data: { status: next } }) };
    };
    initializeClientRecharge(root, environment, request);
    initializeClientRecharge(root, environment, request);
    return { poll: () => scheduled(), refresh: () => refresh(), root, listeners, message, requests: () => requests, reloads: () => reloads };
}

test('client polls local status once and reloads only after a status transition', async () => {
    const unchanged = fixture();
    await unchanged.poll();
    assert.equal(unchanged.requests(), 1);
    assert.equal(unchanged.reloads(), 0);
    const paid = fixture('paid');
    await paid.poll();
    assert.equal(paid.reloads(), 1);
});

test('hidden tabs and page departure stop payment status polling', async () => {
    const result = fixture();
    result.root.hidden = true;
    result.refresh();
    assert.equal(result.requests(), 0);
    result.listeners.pagehide();
    result.root.hidden = false;
    result.refresh();
    assert.equal(result.requests(), 0);
    assert.equal(result.reloads(), 1);
});

test('recharge form quick amounts update summary and prevent duplicate submission', () => {
    const listeners = {};
    const formListeners = {};
    const input = {
        value: '10000',
        addEventListener: (name, fn) => {
            listeners[name] = fn;
        },
    };
    const summary = {};
    const submit = {};
    const label = {};
    const preset = {
        dataset: { rechargePreset: '50000' },
        setAttribute: (name, value) => {
            preset[name] = value;
        },
        addEventListener: (_name, fn) => {
            listeners.preset = fn;
        },
    };
    const fields = {
        '[data-recharge-amount]': input,
        '[data-recharge-summary]': summary,
        '[data-recharge-submit]': submit,
        '[data-recharge-submit-label]': label,
    };
    const form = {
        dataset: {},
        querySelector: (selector) => fields[selector],
        querySelectorAll: () => [preset],
        checkValidity: () => Number(input.value.replaceAll('.', '')) >= 10000,
        addEventListener: (name, fn) => {
            formListeners[name] = fn;
        },
    };
    const root = { querySelectorAll: () => [form] };
    const environment = {
        addEventListener: (name, fn) => {
            listeners[name] = fn;
        },
    };
    initializeRechargeForms(root, environment);
    initializeRechargeForms(root, environment);
    listeners.preset();
    assert.equal(input.value, '50.000');
    assert.equal(summary.textContent, '50.000đ');
    assert.equal(preset['aria-pressed'], 'true');
    let prevented = 0;
    const event = {
        preventDefault: () => {
            prevented++;
        },
    };
    formListeners.submit(event);
    assert.equal(submit.disabled, true);
    formListeners.submit(event);
    assert.equal(prevented, 1);
    listeners.pageshow();
    assert.equal(submit.disabled, false);
    input.value = '1';
    listeners.input();
    assert.equal(summary.textContent, '—');
    formListeners.submit(event);
    assert.equal(prevented, 2);
});

test('payment countdown expires without marking paid and copy uses the exact transfer value', async () => {
    const timers = new Map();
    const events = {};
    let id = 0;
    const countdown = {};
    const message = {};
    const expired = {
        classList: {
            remove: (value) => {
                expired.shown = value === 'hidden';
            },
        },
    };
    const qr = {
        setAttribute: () => {
            qr.hidden = true;
        },
    };
    const status = {};
    const copy = {
        dataset: { rechargeCopy: 'TNEXACT' },
        addEventListener: (_name, fn) => {
            events.copy = fn;
        },
    };
    const elements = {
        '[data-recharge-countdown]': countdown,
        '[data-recharge-live-status]': message,
        '[data-recharge-expired]': expired,
        '[data-recharge-qr]': qr,
        '[data-recharge-copy-status]': status,
        '[data-recharge-refresh]': { addEventListener() {} },
    };
    const section = {
        dataset: { status: 'pending', expiresAt: new Date(Date.now() + 60000).toISOString() },
        querySelector: (selector) => elements[selector] ?? null,
        querySelectorAll: () => [copy],
    };
    const root = { querySelectorAll: (selector) => (selector === '[data-wallet-recharge]' ? [section] : []), addEventListener() {} };
    let copied;
    const environment = {
        setTimeout: (fn) => {
            timers.set(++id, fn);
            return id;
        },
        clearTimeout: (key) => timers.delete(key),
        addEventListener: (name, fn) => {
            events[name] = fn;
        },
        navigator: {
            clipboard: {
                writeText: async (value) => {
                    copied = value;
                },
            },
        },
    };
    initializeClientRecharge(root, environment);
    assert.match(countdown.textContent, /^01:00|00:59$/);
    await events.copy();
    assert.equal(copied, 'TNEXACT');
    assert.equal(status.textContent, 'Đã sao chép.');
    section.dataset.expiresAt = new Date(Date.now() - 1).toISOString();
    [...timers.values()].at(-1)();
    assert.equal(countdown.textContent, '00:00');
    assert.equal(qr.hidden, true);
    assert.equal(copy.disabled, true);
    assert.equal(expired.shown, true);
    assert.equal(section.dataset.status, 'pending');
    events.pagehide();
    assert.equal(timers.size, 0);
});
