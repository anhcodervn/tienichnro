import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeGameImageAccounts } from '../../resources/js/client-game-image-accounts.js';

test('username eye toggles the masked and full text without interpreting HTML or double binding', () => {
    let click;
    let bindings = 0;
    const attributes = { 'aria-pressed': 'false' };
    const classes = new Set(['bx-eye']);
    const value = { textContent: 'abc***xyz' };
    const cell = { dataset: { username: 'abc<script>xyz', maskedUsername: 'abc***xyz' }, querySelector: () => value };
    const button = {
        dataset: {},
        closest: () => cell,
        getAttribute: (key) => attributes[key],
        setAttribute: (key, content) => (attributes[key] = content),
        querySelector: () => ({ classList: { toggle: (key, on) => (on ? classes.add(key) : classes.delete(key)) } }),
        addEventListener: (_, handler) => {
            click = handler;
            bindings++;
        },
    };
    const root = { querySelectorAll: (selector) => (selector === '[data-game-image-username-toggle]' ? [button] : []) };
    initializeGameImageAccounts(root);
    initializeGameImageAccounts(root);
    assert.equal(bindings, 1);
    click();
    assert.equal(value.textContent, 'abc<script>xyz');
    assert.equal(value.innerHTML, undefined);
    assert.equal(attributes['aria-pressed'], 'true');
    assert.equal(classes.has('bx-eye-slash'), true);
    click();
    assert.equal(value.textContent, 'abc***xyz');
    assert.equal(attributes['aria-pressed'], 'false');
    assert.equal(classes.has('bx-eye'), true);
});

function recaptureFixture(confirm = true, response = { ok: true, json: async () => ({}) }) {
    let click;
    let reloads = 0;
    const dialogs = [];
    const requests = [];
    const button = {
        dataset: { price: '1500', version: '2', url: '/account/1/chup-lai' },
        disabled: false,
        addEventListener: (_, handler) => (click = handler),
    };
    const root = {
        querySelectorAll: (selector) => (selector === '[data-game-image-recapture]' ? [button] : []),
        querySelector: () => ({ content: 'csrf-token' }),
    };
    const alerts = {
        fire: async (options) => {
            dialogs.push(options);
            return { isConfirmed: confirm };
        },
    };
    const request = async (...args) => {
        requests.push(args);
        return response;
    };
    initializeGameImageAccounts(root, alerts, request, () => reloads++);
    initializeGameImageAccounts(root, alerts, request, () => reloads++);
    return { click: () => click(), button, dialogs, requests, reloads: () => reloads };
}

test('recapture confirms the configured fee before posting once with CSRF and capture version', async () => {
    const fixture = recaptureFixture();
    await Promise.all([fixture.click(), fixture.click()]);
    assert.match(fixture.dialogs[0].text, /phí 1\.500đ mỗi lần chụp/);
    assert.equal(fixture.dialogs[0].showCancelButton, true);
    assert.equal(fixture.requests.length, 1);
    const [, options] = fixture.requests[0];
    assert.deepEqual(JSON.parse(options.body), { price: 1500, capture_version: 2 });
    assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.equal(fixture.reloads(), 1);
    assert.equal(fixture.button.disabled, true);
});

test('canceling recapture sends no request and restores the button', async () => {
    const fixture = recaptureFixture(false);
    await fixture.click();
    assert.equal(fixture.requests.length, 0);
    assert.equal(fixture.reloads(), 0);
    assert.equal(fixture.button.disabled, false);
});

test('recapture shows server validation errors and allows retry without reloading', async () => {
    const fixture = recaptureFixture(true, { ok: false, json: async () => ({ errors: { price: ['Phí đã thay đổi'] } }) });
    await fixture.click();
    assert.equal(fixture.dialogs[1].text, 'Phí đã thay đổi');
    assert.equal(fixture.dialogs[1].icon, 'error');
    assert.equal(fixture.reloads(), 0);
    assert.equal(fixture.button.disabled, false);
});
