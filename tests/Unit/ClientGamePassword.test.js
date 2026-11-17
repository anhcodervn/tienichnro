import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeGamePassword, initializeGamePasswordCaptcha, initializeGamePasswordLogModal, renderGamePasswordHistory, retrySavedGamePassword } from '../../resources/js/client-game-password.js';

test('bulk checkbox switches required fields and calculates the batch price', () => {
    const handlers = {};
    const toggle = { checked: false, addEventListener: (name, fn) => (handlers[name] = fn) };
    const fields = [{}, {}, {}, {}];
    const oldPassword = { type: 'password', value: 'old-secret' };
    const newPassword = { type: 'password', value: 'new-secret' };
    const single = { querySelectorAll: () => fields };
    const textarea = { value: '', addEventListener: (_name, fn) => (handlers.input = fn) };
    const bulk = { querySelector: () => textarea };
    const total = {};
    const label = {};
    const button = { querySelector: () => label };
    const events = {};
    const form = {
        dataset: { price: '100' },
        querySelector: (selector) =>
            ({
                '[data-password-bulk-toggle]': toggle,
                '[data-password-single]': single,
                '[data-password-bulk]': bulk,
                '[data-password-total]': total,
                'button[type="submit"]': button,
                '#game-password-old': oldPassword,
                '#game-password-new': newPassword,
            })[selector],
        addEventListener: (name, fn) => (handlers[name] = fn),
    };
    const root = { querySelector: (selector) => (selector === '[data-password-form]' ? form : null) };
    initializeGamePassword(root, { addEventListener: (name, fn) => (events[name] = fn) });
    assert.equal(single.hidden, false);
    assert.equal(textarea.disabled, true);
    assert.equal(label.textContent, 'Đổi mật khẩu · 1 tài khoản');
    const eye = {
        dataset: { passwordVisibility: 'game-password-old', passwordLabel: 'mật khẩu cũ' },
        attributes: {},
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
    };
    handlers.click({ target: { closest: (selector) => (selector === '[data-password-visibility]' ? eye : null) } });
    assert.equal(oldPassword.type, 'text');
    assert.equal(newPassword.type, 'password');
    assert.equal(eye.attributes['aria-label'], 'Ẩn mật khẩu cũ');
    assert.equal(eye.attributes['aria-pressed'], 'true');
    handlers.click({ target: { closest: (selector) => (selector === '[data-password-visibility]' ? eye : null) } });
    assert.equal(oldPassword.type, 'password');
    assert.equal(eye.attributes['aria-pressed'], 'false');
    eye.dataset.passwordVisibility = 'game-password-new';
    eye.dataset.passwordLabel = 'mật khẩu mới';
    handlers.click({ target: { closest: (selector) => (selector === '[data-password-visibility]' ? eye : null) } });
    assert.equal(newPassword.type, 'text');
    assert.equal(oldPassword.type, 'password');
    assert.equal(eye.attributes['aria-label'], 'Ẩn mật khẩu mới');
    assert.equal(oldPassword.value, 'old-secret');
    assert.equal(newPassword.value, 'new-secret');
    toggle.checked = true;
    handlers.change();
    assert.equal(button.disabled, true);
    toggle.checked = true;
    textarea.value = `one|old|3|new
two|old|sv3|new`;
    handlers.change();
    assert.equal(single.hidden, true);
    assert.equal(bulk.hidden, false);
    assert.ok(fields.every((field) => field.disabled));
    assert.equal(textarea.required, true);
    assert.match(total.textContent, /2 tài khoản.*200/);
    assert.equal(label.textContent, 'Đổi mật khẩu · 2 tài khoản');
    assert.equal(button.disabled, false);
    handlers.submit();
    assert.equal(button.disabled, true);
    assert.equal(label.textContent, 'Đang gửi yêu cầu…');
    events.pageshow();
    assert.equal(button.disabled, false);
    assert.equal(label.textContent, 'Đổi mật khẩu · 2 tài khoản');
    textarea.value = Array(101).fill('one|old|3|new').join('\n');
    handlers.input();
    assert.equal(button.disabled, true);
    form.dataset.price = '';
    handlers.input();
    assert.match(total.textContent, /Chưa cấu hình giá/);
});

test('retry posts only the order attempt and prevents duplicate clicks without asking for passwords', async () => {
    const button = { disabled: false, dataset: { retryUrl: '/password-tool/42/retry', attemptVersion: '2' } };
    const calls = [];
    const request = async (url, options) => {
        calls.push({ url, options });
        return { ok: true, json: async () => ({ data: { status: 'pending' } }) };
    };
    assert.equal(await retrySavedGamePassword(button, 'csrf-token', request), true);
    assert.equal(calls[0].url, '/password-tool/42/retry');
    assert.deepEqual(JSON.parse(calls[0].options.body), { attempt_version: 2 });
    assert.equal(calls[0].options.headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.equal(button.disabled, true);
    assert.equal(await retrySavedGamePassword(button, 'csrf-token', request), false);
    assert.equal(calls.length, 1);
    const failed = { disabled: false, dataset: button.dataset };
    await assert.rejects(
        retrySavedGamePassword(failed, 'csrf-token', async () => ({ ok: false, json: async () => ({ message: 'Missing saved credentials' }) })),
        /Missing saved credentials/,
    );
    assert.equal(failed.disabled, false);
});

test('log updates follow the latest line while retaining readers scroll position and table scroll', () => {
    const makeLog = (id, top) => ({ dataset: { passwordLog: id }, scrollTop: top, scrollHeight: 400, clientHeight: 100 });
    const oldLogs = [makeLog('1', 300), makeLog('2', 40)];
    const newLogs = [makeLog('1', 0), makeLog('2', 0), makeLog('3', 0)];
    let logs = oldLogs;
    const content = {
        firstElementChild: { scrollLeft: 120 },
        querySelectorAll: () => logs,
        set innerHTML(value) {
            assert.equal(value, '<table>updated</table>');
            logs = newLogs;
            this.firstElementChild = { scrollLeft: 0 };
        },
    };
    renderGamePasswordHistory(content, '<table>updated</table>');
    assert.equal(newLogs[0].scrollTop, 400);
    assert.equal(newLogs[1].scrollTop, 40);
    assert.equal(newLogs[2].scrollTop, 400);
    assert.equal(content.firstElementChild.scrollLeft, 120);
});

test('retry can replace personal captcha keys without sending account passwords', async () => {
    const button = { disabled: false, dataset: { retryUrl: '/retry/1', attemptVersion: '2' } };
    await retrySavedGamePassword(button, 'csrf', async (_url, options) => {
        assert.deepEqual(JSON.parse(options.body), { attempt_version: 2, captcha_api_key: 'ak_user', captcha_api_secret: 'sk_user' });
        return { ok: true, json: async () => ({ data: { status: 'pending' } }) };
    }, { key: 'ak_user', secret: 'sk_user' });
});

test('terminal modal opens the selected account safely and follows realtime logs without stealing scroll', () => {
    const events = {};
    let click;
    let closeClick;
    let focused = 0;
    const title = {};
    const terminal = { textContent: '', scrollTop: 0, scrollHeight: 400, clientHeight: 100 };
    const button = { dataset: { passwordViewLog: '12' }, focus: () => focused++ };
    let logs = [{ dataset: { passwordLog: '12' }, value: '[12:00] <script>alert(1)</script>' }, { dataset: { passwordLog: '99' }, value: 'unrelated account' }];
    const content = { querySelectorAll: (selector) => selector === '[data-password-log]' ? logs : [button] };
    const dialog = {
        open: false,
        showModal() { this.open = true; },
        close() { this.open = false; events.close(); },
        querySelector: (selector) => ({
            '[data-password-log-terminal]': terminal,
            '[data-password-log-title]': title,
            '[data-password-log-close]': { addEventListener: (_name, fn) => closeClick = fn },
        })[selector],
        addEventListener: (name, fn) => events[name] = fn,
        getBoundingClientRect: () => ({ left: 10, right: 100, top: 10, bottom: 100 }),
    };
    const root = { querySelector: () => dialog, documentElement: { style: { overflow: 'auto' } } };
    const modal = initializeGamePasswordLogModal(root, { addEventListener: (_name, fn) => click = fn }, content);
    click({ target: { closest: () => button } });
    assert.equal(dialog.open, true);
    assert.equal(root.documentElement.style.overflow, 'hidden');
    assert.match(title.textContent, /#12/);
    assert.equal(terminal.textContent, logs[0].value);
    assert.equal(terminal.scrollTop, 400);
    terminal.scrollTop = 40;
    logs = [{ dataset: { passwordLog: '12' }, value: 'new realtime line' }];
    modal.sync();
    assert.equal(terminal.textContent, 'new realtime line');
    assert.equal(terminal.scrollTop, 40);
    terminal.scrollTop = 300;
    logs[0].value += '\ndone';
    modal.sync();
    assert.equal(terminal.scrollTop, 400);
    events.click({ target: dialog, clientX: 30, clientY: 30 });
    assert.equal(dialog.open, true);
    events.click({ target: dialog, clientX: 1, clientY: 1 });
    assert.equal(dialog.open, false);
    assert.equal(root.documentElement.style.overflow, 'auto');
    assert.equal(terminal.textContent, '');
    assert.equal(focused, 1);
    click({ target: { closest: () => button } });
    closeClick();
    assert.equal(dialog.open, false);
    click({ target: { closest: () => button } });
    modal.close();
    assert.equal(dialog.open, false);
});

function captchaProfileFixture(saved, request) {
    const handlers = {};
    const key = { value: '', disabled: saved, required: !saved, reportValidity: () => key.value !== '', focus() {} };
    const secret = { value: '', disabled: saved, required: !saved, reportValidity: () => secret.value !== '' };
    const profile = { textContent: '', hidden: !saved };
    const message = {};
    const buttons = Object.fromEntries(['save', 'edit', 'cancel', 'refresh'].map((name) => [name, { addEventListener: (_event, fn) => handlers[name] = fn }]));
    const form = { querySelector: () => ({ value: 'csrf-token' }), addEventListener: (name, fn) => handlers[name] = fn };
    const panel = {
        dataset: { saved: String(saved), saveUrl: '/captcha', refreshUrl: '/captcha/refresh' },
        closest: () => form,
        querySelector: (selector) => ({
            '[name="captcha_api_key"]': key,
            '[name="captcha_api_secret"]': secret,
            '[data-password-captcha-profile]': profile,
            '[data-password-captcha-message]': message,
            ...Object.fromEntries(Object.entries(buttons).map(([name, value]) => [`[data-password-captcha-${name}]`, value])),
        })[selector],
    };
    initializeGamePasswordCaptcha({ querySelector: () => panel }, request);
    return { handlers, key, secret, profile, message, buttons, panel };
}

test('saving captcha keys checks only that panel clears secret inputs and displays safe profile text', async () => {
    let calls = 0;
    let complete;
    const pending = new Promise((resolve) => complete = resolve);
    const f = captchaProfileFixture(false, async (url, options) => {
        calls++;
        assert.equal(url, '/captcha');
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-token');
        assert.deepEqual(JSON.parse(options.body), { captcha_api_key: 'ak_user', captcha_api_secret: 'sk_user' });
        await pending;
        return { ok: true, json: async () => ({ data: { saved: true, username: '<img src=x>', balance: '1500' } }) };
    });
    assert.equal(calls, 0);
    await f.handlers.save();
    assert.equal(calls, 0);
    f.key.value = 'ak_user';
    f.secret.value = 'sk_user';
    const saving = f.handlers.save();
    let prevented = false;
    f.handlers.submit({ preventDefault: () => prevented = true });
    assert.equal(prevented, true);
    await f.handlers.save();
    assert.equal(calls, 1);
    complete();
    await saving;
    assert.equal(f.profile.textContent, '<img src=x> • 1.500 đ');
    assert.equal(f.key.value, '');
    assert.equal(f.secret.value, '');
    assert.equal(f.key.placeholder, '***');
    assert.equal(f.secret.disabled, true);
    assert.equal(f.secret.required, false);
    assert.equal(f.buttons.save.hidden, true);
    assert.equal(f.buttons.edit.hidden, false);
});

test('saved captcha profile can refresh without returning keys and failed replacement keeps the inputs', async () => {
    let fail = false;
    const f = captchaProfileFixture(true, async (url, options) => {
        if (url.endsWith('/refresh')) {
            assert.deepEqual(JSON.parse(options.body), {});
            return { ok: true, json: async () => ({ data: { username: 'demo_user', balance: '0' } }) };
        }
        fail = true;
        return { ok: false, json: async () => ({ errors: { captcha_api_key: ['Invalid key'] } }) };
    });
    await f.handlers.refresh();
    assert.equal(f.profile.textContent, 'demo_user • 0 đ');
    f.handlers.edit();
    assert.equal(f.key.disabled, false);
    f.key.value = 'bad-key';
    f.secret.value = 'bad-secret';
    await f.handlers.save();
    assert.equal(fail, true);
    assert.equal(f.message.textContent, 'Invalid key');
    assert.equal(f.secret.value, 'bad-secret');
    assert.equal(f.profile.textContent, 'demo_user • 0 đ');
    f.handlers.cancel();
    assert.equal(f.key.placeholder, '***');
    assert.equal(f.secret.value, '');
    assert.equal(f.secret.disabled, true);
});

function historyFixture(response, realtime = false) {
    const events = {};
    const jobs = new Map();
    let active = true;
    let calls = 0;
    let click;
    const content = { innerHTML: '', querySelectorAll: () => [], querySelector: () => (active ? {} : null) };
    const status = {};
    const section = {
        dataset: { url: '/private/history?status=pending&page=2' },
        addEventListener() {},
        querySelector: (selector) =>
            selector.includes('content') ? content : selector.includes('status') ? status : { addEventListener: (_name, fn) => (click = fn) },
    };
    const environment = {
        AbortController,
        setTimeout: (fn) => {
            jobs.set(1, fn);
            return 1;
        },
        clearTimeout: (key) => jobs.delete(key),
        addEventListener: (name, fn) => (events[name] = fn),
    };
    const streams = [];
    if (realtime) {
        section.dataset.streamUrl = '/private/stream?status=pending&page=2';
        environment.EventSource = class {
            events = {};
            closed = false;
            constructor(url) {
                assert.match(url, /status=pending&page=2/);
                streams.push(this);
            }
            addEventListener(name, callback) {
                this.events[name] = callback;
            }
            close() {
                this.closed = true;
            }
        };
    }
    const root = {
        hidden: false,
        querySelector: (selector) => (selector === '[data-password-history]' ? section : null),
        addEventListener: (name, fn) => (events[name] = fn),
    };
    initializeGamePassword(root, environment, async (url) => {
        calls++;
        assert.match(url, /page=2/);
        return response;
    });
    return { streams, jobs, events, root, content, status, calls: () => calls, click: () => click(), complete: () => (active = false) };
}

test('SSE updates account states without polling and closes on hidden tabs or revoked access', () => {
    const f = historyFixture({ ok: true, status: 200, json: async () => ({ html: 'fallback' }) }, true);
    assert.equal(f.jobs.size, 0);
    assert.equal(f.streams.length, 1);
    const source = f.streams[0];
    source.events.orders({ data: JSON.stringify({ html: '<tr data-password-status="processing">login</tr>' }) });
    assert.match(f.content.innerHTML, /processing/);
    source.events.orders({ data: JSON.stringify({ html: '<tr data-password-status="success">done</tr>' }) });
    assert.match(f.content.innerHTML, /success/);
    f.root.hidden = true;
    f.events.visibilitychange();
    assert.equal(source.closed, true);
    f.root.hidden = false;
    f.events.visibilitychange();
    assert.equal(f.streams.length, 2);
    source.events.orders({ data: JSON.stringify({ html: 'stale packet' }) });
    assert.match(f.content.innerHTML, /success/);
    f.streams[1].events['access-expired']();
    assert.equal(f.streams[1].closed, true);
    assert.match(f.status.textContent, /đăng nhập lại/);
    assert.equal(f.jobs.size, 0);
});

test('SSE errors check authentication and navigation closes the connection', async () => {
    const f = historyFixture({ ok: false, status: 401 }, true);
    f.streams[0].onerror();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(f.calls(), 1);
    assert.equal(f.streams[0].closed, true);
    const g = historyFixture({ ok: true, status: 200 }, true);
    g.events.pagehide();
    assert.equal(g.streams[0].closed, true);
    assert.equal(g.jobs.size, 0);
});

test('history updates only its content and stops polling after the visible jobs complete', async () => {
    const f = historyFixture({ ok: true, status: 200, json: async () => ({ html: '<table>completed</table>' }) });
    assert.equal(f.jobs.size, 1);
    f.complete();
    await f.click();
    assert.equal(f.content.innerHTML, '<table>completed</table>');
    assert.equal(f.jobs.size, 0);
    assert.equal(f.calls(), 1);
});

test('hidden tabs and navigation pause polling and expired authentication ends retries', async () => {
    const f = historyFixture({ ok: false, status: 401 });
    f.root.hidden = true;
    f.events.visibilitychange();
    assert.equal(f.jobs.size, 0);
    f.root.hidden = false;
    f.events.visibilitychange();
    assert.equal(f.jobs.size, 1);
    await f.click();
    assert.match(f.status.textContent, /đăng nhập lại/);
    assert.equal(f.jobs.size, 0);
});
