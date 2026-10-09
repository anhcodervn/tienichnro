import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../../resources/js/client-auth.js', import.meta.url), 'utf8');
const { initializeClientAuth } = await import(`data:text/javascript,${encodeURIComponent(source.replace(/^import .*$/gm, ''))}`);

function environment(post) {
    const attributes = {};
    const input = {
        value: 'player@example.com',
        setAttribute: (key, value) => (attributes[key] = value),
        removeAttribute: (key) => delete attributes[key],
        focus: () => (input.focused = true),
    };
    const button = { textContent: 'Đăng nhập', disabled: false };
    const form = {
        dataset: {},
        action: 'https://example.com/dang-nhap',
        elements: { namedItem: (name) => (name === 'login' ? input : null) },
        querySelector: () => button,
        querySelectorAll: () => [input],
        reportValidity: () => true,
        setAttribute: (key, value) => (attributes[key] = value),
        removeAttribute: (key) => delete attributes[key],
        addEventListener: (name, handler) => (form.submit = handler),
    };
    const calls = [];
    const alerts = [];
    const redirects = [];
    const root = { querySelectorAll: () => [form] };
    const http = {
        post: (...args) => {
            calls.push(args);
            return post(...args);
        },
    };
    const notifications = { fire: async (options) => alerts.push(options) };
    initializeClientAuth(root, http, notifications, (url) => redirects.push(url));
    initializeClientAuth(root, http, notifications, (url) => redirects.push(url));
    return { form, button, input, attributes, calls, alerts, redirects, submit: () => form.submit({ preventDefault() {} }) };
}

// A minimal FormData substitute captures the submitted form without requiring a browser DOM.
globalThis.FormData = class {
    constructor(form) {
        this.form = form;
    }
};

test('ajax submission sends the form, blocks duplicate requests and waits for success before navigating', async () => {
    let resolve;
    const env = environment(
        () =>
            new Promise((done) => {
                resolve = done;
            }),
    );
    const first = env.submit();
    await env.submit();
    assert.equal(env.calls.length, 1);
    assert.equal(env.calls[0][0], env.form.action);
    assert.equal(env.calls[0][1].form, env.form);
    assert.equal(env.calls[0][2].headers.Accept, 'application/json');
    assert.equal(env.button.disabled, true);
    assert.equal(env.attributes['aria-busy'], 'true');
    assert.deepEqual(env.redirects, []);
    resolve({ data: { status: true, message: 'Success', redirect: '/tai-khoan' } });
    await first;
    assert.equal(env.alerts[0].icon, 'success');
    assert.deepEqual(env.redirects, ['/tai-khoan']);
    assert.equal(env.button.disabled, true);
});

test('validation errors use safe text, preserve values, focus invalid input and allow retry', async () => {
    const env = environment(async () => {
        throw { response: { status: 422, data: { errors: { login: ['<img onerror=alert(1)>'] } } } };
    });
    await env.submit();
    assert.equal(env.alerts[0].text, '<img onerror=alert(1)>');
    assert.equal(env.alerts[0].html, undefined);
    assert.equal(env.input.value, 'player@example.com');
    assert.equal(env.input.focused, true);
    assert.equal(env.attributes['aria-invalid'], 'true');
    assert.equal(env.attributes['aria-busy'], undefined);
    assert.equal(env.button.disabled, false);
    assert.equal(env.button.textContent, 'Đăng nhập');
    assert.deepEqual(env.redirects, []);
    await env.submit();
    assert.equal(env.calls.length, 2);
});

for (const [status, message] of [
    [419, 'Phiên làm việc'],
    [429, 'quá nhiều lần'],
    [500, 'kết nối'],
    [undefined, 'kết nối'],
]) {
    test(`request failure ${status} restores the submit button and explains recovery`, async () => {
        const env = environment(async () => {
            throw { response: { status } };
        });
        await env.submit();
        assert.ok(env.alerts[0].text.includes(message));
        assert.equal(env.button.disabled, false);
        assert.deepEqual(env.redirects, []);
    });
}

test('invalid form is not submitted and unexpected HTML response is recoverable', async () => {
    const env = environment(async () => ({ data: '<html>Login</html>' }));
    env.form.reportValidity = () => false;
    await env.submit();
    assert.equal(env.calls.length, 0);
    env.form.reportValidity = () => true;
    await env.submit();
    assert.equal(env.alerts[0].icon, 'error');
    assert.equal(env.button.disabled, false);
    assert.deepEqual(env.redirects, []);
});
