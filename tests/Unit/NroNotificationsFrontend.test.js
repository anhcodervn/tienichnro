import DOMPurify from 'dompurify';
import assert from 'node:assert/strict';
import test from 'node:test';
import { formatNroRelativeTime, initializeNroFilters, initializeNroNotifications } from '../../resources/js/nro-notifications.js';

function ajaxFixture() {
    const keys = ['document', 'window', 'HTMLElement', 'EventSource', 'FormData', 'fetch'];
    const originals = Object.fromEntries(keys.map((key) => [key, globalThis[key]]));
    const sanitize = DOMPurify.sanitize;
    const handlers = {};
    const requests = [];
    const streams = [];
    const attributes = {};
    const reloads = { count: 0 };
    const controls = Object.fromEntries(
        ['server_id', 'code', 'boss_id', 'state', 'q', 'limit'].map((name) => [
            name,
            { value: name === 'limit' ? '10' : '', options: [{ value: '10' }] },
        ]),
    );
    const submit = { disabled: false };
    const form = {
        action: 'https://example.test/thong-bao-game',
        elements: { namedItem: (name) => controls[name] },
        querySelector: () => submit,
        addEventListener: (name, callback) => (handlers[name] = callback),
    };
    const list = { innerHTML: 'Original rows', querySelectorAll: () => [], classList: { toggle: () => {} } };
    const pagination = { innerHTML: '', classList: { toggle: () => {} }, addEventListener: (name, callback) => (handlers.pagination = callback) };
    const loading = { hidden: true };
    const error = { hidden: true };
    const refresh = { href: form.action, addEventListener: (name, callback) => (handlers.refresh = callback) };
    class Element {
        dataset = { nroClock: '2026-10-08T11:54:00+07:00', nroStream: '/stream?limit=10', nroSignature: 'initial' };
        querySelector(selector) {
            return (
                {
                    '[data-nro-list]': list,
                    '[data-nro-pagination]': pagination,
                    '[data-nro-filters]': form,
                    '[data-nro-loading]': loading,
                    '[data-nro-error]': error,
                    '[data-nro-refresh]': refresh,
                }[selector] || null
            );
        }
        querySelectorAll() {
            return [];
        }
        setAttribute(name, value) {
            attributes[name] = value;
        }
    }
    const root = new Element();
    DOMPurify.sanitize = (html) => html;
    globalThis.HTMLElement = Element;
    globalThis.document = { querySelector: () => root };
    globalThis.window = {
        location: { href: form.action, origin: 'https://example.test', reload: () => ++reloads.count },
        history: { pushState: (_, __, url) => (window.location.href = url) },
        setInterval: () => 1,
        clearInterval: () => {},
        addEventListener: (name, callback) => (handlers[name] = callback),
        EventSource: true,
    };
    globalThis.FormData = class {
        *[Symbol.iterator]() {
            for (const [name, input] of Object.entries(controls)) {
                yield [name, input.value];
            }
        }
    };
    globalThis.EventSource = class {
        constructor(url) {
            this.url = url;
            this.handlers = {};
            streams.push(this);
        }
        addEventListener(name, callback) {
            this.handlers[name] = callback;
        }
        close() {
            this.closed = true;
        }
    };
    globalThis.fetch = (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve, reject }));
    initializeNroNotifications();
    return {
        handlers,
        requests,
        streams,
        list,
        loading,
        error,
        controls,
        attributes,
        reloads,
        root,
        refresh,
        restore: () => {
            for (const key of keys) globalThis[key] = originals[key];
            DOMPurify.sanitize = sanitize;
        },
    };
}

function ajaxResponse(query, html) {
    return {
        ok: true,
        json: async () => ({
            data: {
                html,
                pagination: '<nav>2 pages</nav>',
                count: 10,
                total: 20,
                per_page: 10,
                signature: html,
                server_time: '2026-10-08T11:54:00+07:00',
            },
            filters: Object.fromEntries(new URLSearchParams(query)),
            url: `https://example.test/thong-bao-game?${query}`,
            stream_url: `https://example.test/stream?${query}`,
        }),
    };
}

const settleAjax = () => new Promise((resolve) => setImmediate(resolve));

test('expired realtime access closes SSE and reloads without accepting late events', () => {
    const fixture = ajaxFixture();
    try {
        fixture.streams[0].handlers['access-expired']({ data: '{}' });
        assert.equal(fixture.streams[0].closed, true);
        assert.equal(fixture.reloads.count, 1);
        fixture.streams[0].handlers.notifications({ data: JSON.stringify({ html: 'Private data', signature: 'late' }) });
        assert.equal(fixture.list.innerHTML, 'Original rows');
    } finally {
        fixture.restore();
    }
});

test('SSE rate limits stop reconnection and expired grants trigger a preview reload', async () => {
    for (const [status, payload, reloads] of [
        [429, {}, 0],
        [200, { realtime: false }, 1],
    ]) {
        const fixture = ajaxFixture();
        try {
            const checking = fixture.streams[0].handlers.error();
            fixture.requests[0].resolve({ status, json: async () => payload });
            await checking;
            assert.equal(fixture.streams[0].closed, true);
            assert.equal(fixture.reloads.count, reloads);
            assert.equal(fixture.streams.length, 1);
        } finally {
            fixture.restore();
        }
    }
});

test('maintenance SSE closes the live stream and reloads the page without reconnecting', () => {
    const fixture = ajaxFixture();
    try {
        fixture.streams[0].handlers.maintenance({ data: JSON.stringify({ message: 'Maintenance' }) });
        assert.equal(fixture.streams[0].closed, true);
        assert.equal(fixture.reloads.count, 1);
        assert.equal(fixture.root.dataset.nroStream, undefined);
        fixture.streams[0].handlers.notifications({ data: JSON.stringify({ html: 'Late rows', signature: 'late' }) });
        fixture.handlers.pageshow({ persisted: true });
        fixture.handlers.submit({ preventDefault: () => {} });
        assert.equal(fixture.list.innerHTML, 'Original rows');
        assert.equal(fixture.streams.length, 1);
        assert.equal(fixture.requests.length, 0);
    } finally {
        fixture.restore();
    }
});

test('maintenance JSON aborts concurrent requests and replaces interaction by reloading', async () => {
    const fixture = ajaxFixture();
    try {
        fixture.handlers.submit({ preventDefault: () => {} });
        fixture.requests[0].resolve({ ok: false, status: 503, json: async () => ({ service_maintenance: true, message: 'Maintenance' }) });
        await settleAjax();
        assert.equal(fixture.requests[0].options.signal.aborted, true);
        assert.equal(fixture.reloads.count, 1);
        assert.equal(fixture.streams.length, 1);
        assert.equal(fixture.list.innerHTML, 'Original rows');
        fixture.handlers.popstate();
        assert.equal(fixture.requests.length, 1);
    } finally {
        fixture.restore();
    }
});

test('SSE connection failures check maintenance through JSON and reload only for a disabled service', async () => {
    const fixture = ajaxFixture();
    try {
        const first = fixture.streams[0].handlers.error();
        fixture.requests[0].resolve({ status: 503, json: async () => ({ message: 'Temporary network error' }) });
        await first;
        assert.equal(fixture.reloads.count, 0);
        const second = fixture.streams[0].handlers.error();
        fixture.requests[1].resolve({ status: 503, json: async () => ({ service_maintenance: true }) });
        await second;
        assert.equal(fixture.reloads.count, 1);
        assert.equal(fixture.streams[0].closed, true);
    } finally {
        fixture.restore();
    }
});

test('relative timestamps use the expected Vietnamese minute hour and day labels', () => {
    const current = Date.parse('2026-10-08T11:54:00+07:00');
    for (const [seconds, expected] of [
        [0, 'Vừa cập nhật'],
        [59, 'Vừa cập nhật'],
        [60, '1 phút trước'],
        [120, '2 phút trước'],
        [3600, '1 giờ trước'],
        [86400, '1 ngày trước'],
    ]) {
        assert.equal(formatNroRelativeTime(new Date(current - seconds * 1000).toISOString(), current), expected);
    }
    assert.equal(formatNroRelativeTime('invalid', current), 'Không xác định');
    assert.equal(formatNroRelativeTime(new Date(current + 1000).toISOString(), current), 'Sắp tới');
});

test('type changes hide disable and clear obsolete supplementary values', () => {
    let change;
    const type = {
        value: 'BOSS',
        selectedOptions: [{ dataset: { additionalFilters: '["boss","state"]' } }],
        addEventListener: (_, callback) => {
            change = callback;
        },
    };
    const controls = ['boss', 'state'].map((name) => ({
        dataset: { nroExtra: name },
        input: { value: 'chosen' },
        querySelectorAll() {
            return [this.input];
        },
    }));
    const group = {};
    const root = {
        querySelector: (selector) => ({ '[data-nro-type]': type, '[data-nro-extra-group]': group })[selector] ?? null,
        querySelectorAll: () => controls,
    };
    initializeNroFilters(root);
    assert.equal(group.hidden, false);
    assert.equal(controls[0].hidden, false);
    assert.equal(controls[0].input.disabled, false);
    assert.equal(controls[0].input.value, 'chosen');
    type.value = 'OTHER';
    type.selectedOptions[0].dataset.additionalFilters = '[]';
    change();
    for (const control of controls) {
        assert.equal(control.hidden, true);
        assert.equal(control.input.disabled, true);
        assert.equal(control.input.value, '');
    }
    assert.equal(group.hidden, true);
    type.value = 'BOSS';
    type.selectedOptions[0].dataset.additionalFilters = '["boss"]';
    change();
    assert.equal(controls[0].hidden, false);
    assert.equal(controls[1].hidden, true);
    assert.equal(group.hidden, false);
    type.selectedOptions[0].dataset.additionalFilters = 'invalid-json';
    change();
    assert.equal(group.hidden, true);
});

test('relative labels update on initial load and SSE heartbeats using server time', () => {
    const originalDocument = globalThis.document;
    const originalWindow = globalThis.window;
    const originalHTMLElement = globalThis.HTMLElement;
    const originalEventSource = globalThis.EventSource;
    const listeners = {};
    const timestamps = [{ dataset: { nroRelative: '2026-10-08T11:53:00+07:00' } }, { dataset: { nroRelative: '2026-10-08T11:53:50+07:00' } }];
    const respawns = [{ dataset: { nroRespawn: '2026-10-08T11:55:00+07:00' } }];
    class Element {
        dataset = { nroClock: '2026-10-08T11:54:00+07:00', nroStream: '/stream' };
        querySelector() {
            return null;
        }
        querySelectorAll(selector) {
            return selector === '[data-nro-relative]' ? timestamps : selector === '[data-nro-respawn]' ? respawns : [];
        }
    }
    try {
        globalThis.HTMLElement = Element;
        globalThis.document = { querySelector: () => new Element() };
        globalThis.window = { setInterval: () => 1, addEventListener: () => {}, EventSource: true };
        globalThis.EventSource = class {
            addEventListener(name, listener) {
                listeners[name] = listener;
            }
        };
        initializeNroNotifications();
        assert.equal(timestamps[0].textContent, ' - 1 phút trước');
        assert.equal(timestamps[1].textContent, ' - Vừa cập nhật');
        assert.equal(respawns[0].textContent, 'Còn 1 phút 0 giây');
        listeners.heartbeat({ data: JSON.stringify({ server_time: '2026-10-08T11:55:00+07:00' }) });
        assert.equal(timestamps[0].textContent, ' - 2 phút trước');
        assert.equal(timestamps[1].textContent, ' - 1 phút trước');
        assert.equal(respawns[0].textContent, 'Đã đến giờ dự kiến · chờ thông báo xuất hiện');
    } finally {
        globalThis.document = originalDocument;
        globalThis.window = originalWindow;
        globalThis.HTMLElement = originalHTMLElement;
        globalThis.EventSource = originalEventSource;
    }
});

test('SSE snapshots replace page rows pagination and totals without adding timers', () => {
    const originals = {
        document: globalThis.document,
        window: globalThis.window,
        HTMLElement: globalThis.HTMLElement,
        EventSource: globalThis.EventSource,
        sanitize: DOMPurify.sanitize,
    };
    const listeners = {};
    const list = { innerHTML: '', querySelectorAll: () => [] };
    const pagination = { innerHTML: '', addEventListener: () => {} };
    const count = {};
    const total = {};
    let timerCount = 0;
    class Element {
        dataset = { nroClock: '2026-10-08T11:54:00+07:00', nroStream: '/stream?page=2&limit=10', nroSignature: 'initial' };
        querySelector(selector) {
            return (
                { '[data-nro-list]': list, '[data-nro-pagination]': pagination, '[data-nro-count]': count, '[data-nro-total]': total }[selector] ||
                null
            );
        }
        querySelectorAll() {
            return [];
        }
    }
    try {
        DOMPurify.sanitize = (html) => html;
        globalThis.HTMLElement = Element;
        globalThis.document = { querySelector: () => new Element() };
        globalThis.window = { setInterval: () => ++timerCount, addEventListener: () => {}, EventSource: true };
        globalThis.EventSource = class {
            constructor(url) {
                assert.equal(url, '/stream?page=2&limit=10');
            }
            addEventListener(name, listener) {
                listeners[name] = listener;
            }
        };
        initializeNroNotifications();
        const snapshot = {
            server_time: '2026-10-08T11:54:00+07:00',
            signature: 'page-two',
            html: '<article>Page 2 rows</article>',
            pagination: '<nav>Page 2 / 3</nav>',
            count: 10,
            total: 25,
        };
        listeners.notifications({ data: JSON.stringify(snapshot) });
        assert.equal(list.innerHTML, snapshot.html);
        assert.equal(pagination.innerHTML, snapshot.pagination);
        assert.equal(count.textContent, '10');
        assert.equal(total.textContent, '25');
        listeners.notifications({ data: JSON.stringify({ ...snapshot, signature: 'new-total', total: 31, pagination: '<nav>Page 2 / 4</nav>' }) });
        assert.equal(total.textContent, '31');
        assert.equal(pagination.innerHTML, '<nav>Page 2 / 4</nav>');
        assert.equal(timerCount, 1);
    } finally {
        globalThis.document = originals.document;
        globalThis.window = originals.window;
        globalThis.HTMLElement = originals.HTMLElement;
        globalThis.EventSource = originals.EventSource;
        DOMPurify.sanitize = originals.sanitize;
    }
});

test('AJAX filters show loading abort stale requests and reconnect SSE for the latest result', async () => {
    const fixture = ajaxFixture();
    try {
        fixture.controls.q.value = 'first';
        fixture.handlers.submit({ preventDefault: () => {} });
        assert.equal(fixture.loading.hidden, false);
        assert.equal(fixture.attributes['aria-busy'], 'true');
        assert.equal(fixture.streams[0].closed, true);
        assert.equal(new URL(fixture.requests[0].url).searchParams.get('page'), null);
        assert.equal(fixture.requests[0].options.headers.Accept, 'application/json');
        fixture.controls.q.value = 'second';
        fixture.handlers.submit({ preventDefault: () => {} });
        assert.equal(fixture.requests[0].options.signal.aborted, true);
        fixture.requests[1].resolve(ajaxResponse('q=second&limit=10', 'Latest rows'));
        await settleAjax();
        assert.equal(fixture.list.innerHTML, 'Latest rows');
        assert.equal(fixture.loading.hidden, true);
        assert.equal(fixture.attributes['aria-busy'], 'false');
        assert.equal(fixture.streams[1].url, 'https://example.test/stream?q=second&limit=10');
        assert.equal(window.location.href, 'https://example.test/thong-bao-game?q=second&limit=10');
        fixture.requests[0].resolve(ajaxResponse('q=first&limit=10', 'Stale rows'));
        await settleAjax();
        fixture.streams[0].handlers.notifications({ data: JSON.stringify({ html: 'Stale SSE rows', signature: 'stale' }) });
        assert.equal(fixture.list.innerHTML, 'Latest rows');
        assert.equal(fixture.streams.length, 2);
    } finally {
        fixture.restore();
    }
});

test('AJAX pagination and browser history load in place and failures preserve the last successful page', async () => {
    const fixture = ajaxFixture();
    try {
        fixture.handlers.pagination({
            button: 0,
            target: { closest: () => ({ href: 'https://example.test/thong-bao-game?limit=10&page=2' }) },
            preventDefault: () => {},
        });
        fixture.requests[0].resolve(ajaxResponse('limit=10&page=2', 'Second page rows'));
        await settleAjax();
        assert.equal(fixture.list.innerHTML, 'Second page rows');
        window.location.href = 'https://example.test/thong-bao-game?q=back&limit=10';
        fixture.handlers.popstate();
        fixture.requests[1].resolve(ajaxResponse('q=back&limit=10', 'Back page rows'));
        await settleAjax();
        assert.equal(fixture.controls.q.value, 'back');
        fixture.handlers.refresh({ button: 0, preventDefault: () => {} });
        fixture.requests[2].resolve({ ok: false, json: async () => ({ errors: { limit: ['Invalid limit'] } }) });
        await settleAjax();
        assert.equal(fixture.list.innerHTML, 'Back page rows');
        assert.equal(fixture.error.hidden, false);
        assert.equal(fixture.error.textContent, 'Invalid limit');
        assert.equal(fixture.loading.hidden, true);
        assert.equal(fixture.streams.at(-1).url, 'https://example.test/stream?q=back&limit=10');
    } finally {
        fixture.restore();
    }
});
