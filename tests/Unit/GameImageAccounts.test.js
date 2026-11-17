import assert from 'node:assert/strict';
import test from 'node:test';
import {
    initializeGameImageAccountDetails,
    initializeGameImageAccounts,
    initializeGameImageOrderControls,
} from '../../resources/js/client-game-image-accounts.js';

function controlFixture(action, confirmed = true, ok = true) {
    let click;
    let reloads = 0;
    const requests = [];
    const dialogs = [];
    const button = {
        dataset: { gameImageOrderControl: action, version: '4', fee: '1000', refund: '9000', url: '/account/1/quan-ly' },
        disabled: false,
        addEventListener: (_name, handler) => {
            click = handler;
        },
    };
    const root = { querySelectorAll: () => [button], querySelector: () => ({ content: 'csrf' }) };
    const alerts = {
        fire: async (options) => {
            dialogs.push(options);
            return { isConfirmed: confirmed };
        },
    };
    const request = async (url, options) => {
        requests.push({ url, options });
        return { ok, json: async () => ({ message: 'Stale order' }) };
    };
    initializeGameImageOrderControls(root, alerts, request, () => reloads++);
    initializeGameImageOrderControls(root, alerts, request, () => reloads++);
    return { click: () => click(), button, dialogs, requests, reloads: () => reloads };
}

test('cancel clearly confirms retained fee and refund and sends only action and capture version', async () => {
    const fixture = controlFixture('cancel');
    await fixture.click();
    assert.match(fixture.dialogs[0].text, /1\.000/);
    assert.match(fixture.dialogs[0].text, /9\.000/);
    assert.deepEqual(JSON.parse(fixture.requests[0].options.body), { action: 'cancel', capture_version: 4 });
    assert.equal(fixture.requests[0].options.headers['X-CSRF-TOKEN'], 'csrf');
    assert.equal(fixture.requests[0].options.credentials, 'same-origin');
    assert.equal(fixture.reloads(), 1);
});

test('pause and resume send versioned requests and declined or stale controls remain retryable', async () => {
    for (const action of ['pause', 'resume']) {
        const fixture = controlFixture(action);
        await fixture.click();
        assert.deepEqual(JSON.parse(fixture.requests[0].options.body), { action, capture_version: 4 });
    }
    const declined = controlFixture('cancel', false);
    await declined.click();
    assert.equal(declined.requests.length, 0);
    assert.equal(declined.button.disabled, false);
    const failed = controlFixture('pause', true, false);
    await failed.click();
    assert.equal(failed.dialogs[1].text, 'Stale order');
    assert.equal(failed.reloads(), 0);
    assert.equal(failed.button.disabled, false);
});

function detailsFixture(ok = true) {
    const events = {};
    const requests = [];
    let reloads = 0;
    let click;
    let submit;
    const fields = {
        username: { value: '', focus() {} },
        password: { value: '', type: 'password' },
        server: {
            value: '',
            options: [{ value: '3' }],
            querySelector: () => null,
            appendChild(option) {
                this.options.push(option);
            },
        },
    };
    const error = { textContent: '' };
    const eye = { addEventListener() {}, setAttribute() {} };
    const form = {
        elements: { namedItem: (name) => fields[name] },
        querySelectorAll: () => Object.values(fields),
        reportValidity: () => true,
        addEventListener: (_name, handler) => {
            submit = handler;
        },
    };
    const dialog = {
        dataset: {},
        open: false,
        showModal() {
            this.open = true;
        },
        close() {
            this.open = false;
            events.close();
        },
        querySelector: (selector) => (selector.endsWith('form]') ? form : selector.endsWith('error]') ? error : eye),
        querySelectorAll: () => [],
        addEventListener: (name, handler) => {
            events[name] = handler;
        },
    };
    const button = { dataset: { username: 'my-account', server: '3', version: '2', url: '/account/1/thong-tin' }, disabled: false };
    const section = {
        addEventListener: (_name, handler) => {
            click = handler;
        },
    };
    const root = {
        querySelector: (selector) => (selector.includes('modal') ? dialog : selector.includes('accounts]') ? section : { content: 'csrf' }),
        documentElement: { style: { overflow: 'auto' } },
        createElement: () => ({ dataset: {} }),
    };
    const request = async (url, options) => {
        requests.push({ url, options });
        return { ok, json: async () => (ok ? {} : { message: 'Capture changed' }) };
    };
    initializeGameImageAccountDetails(root, request, () => reloads++);
    initializeGameImageAccountDetails(root, request, () => reloads++);
    return {
        fields,
        error,
        dialog,
        requests,
        open: () => click({ target: { closest: () => button } }),
        submit: () => submit({ preventDefault() {} }),
        reloads: () => reloads,
    };
}

test('account correction pre-fills identity without passwords and PATCHes with CSRF and current version', async () => {
    const fixture = detailsFixture();
    fixture.open();
    assert.equal(fixture.fields.username.value, 'my-account');
    assert.equal(fixture.fields.password.value, '');
    fixture.fields.username.value = 'corrected';
    await fixture.submit();
    assert.equal(fixture.requests.length, 1);
    assert.equal(fixture.requests[0].options.method, 'PATCH');
    assert.equal(fixture.requests[0].options.headers['X-CSRF-TOKEN'], 'csrf');
    assert.deepEqual(JSON.parse(fixture.requests[0].options.body), { username: 'corrected', server: '3', capture_version: 2 });
    assert.equal(fixture.dialog.open, false);
    assert.equal(fixture.reloads(), 1);
});

test('account correction keeps the form open on stale errors and allows retry', async () => {
    const fixture = detailsFixture(false);
    fixture.open();
    fixture.fields.password.value = 'new-secret';
    await fixture.submit();
    assert.equal(fixture.dialog.open, true);
    assert.equal(fixture.error.textContent, 'Capture changed');
    assert.equal(fixture.fields.password.disabled, false);
    assert.equal(fixture.reloads(), 0);
    assert.equal(JSON.parse(fixture.requests[0].options.body).password, 'new-secret');
});

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
