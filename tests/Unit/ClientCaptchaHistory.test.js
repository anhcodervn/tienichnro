import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeCaptchaHistory } from '../../resources/js/client-captcha.js';

function element() {
    return {
        children: [],
        handlers: {},
        attributes: {},
        textContent: '',
        disabled: false,
        appendChild(child) {
            this.children.push(child);
        },
        replaceChildren() {
            this.children = [];
        },
        addEventListener(name, handler) {
            this.handlers[name] = handler;
        },
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
    };
}

function fixture(request) {
    const nodes = new Map();
    const get = (selector) => {
        if (!nodes.has(selector)) nodes.set(selector, element());
        return nodes.get(selector);
    };
    const root = { dataset: { historyUrl: '/history' }, querySelector: get, querySelectorAll: () => [] };
    const errors = [];
    return {
        get,
        errors,
        load: initializeCaptchaHistory(
            root,
            request,
            () => {},
            (error) => errors.push(error),
        ),
    };
}

function response(url, result = '<img src=x onerror=alert(1)>') {
    const query = new URL(url, 'https://example.test').searchParams;
    const page = (name) => ({
        current_page: Number(query.get(name + '_page')),
        last_page: 2,
        total: 21,
        from: 1,
        to: 20,
        data: [
            {
                id: 1,
                result,
                status: 'success',
                source: 'api',
                duration_ms: 842,
                created_at: '2026-11-16T00:00:00Z',
                direction: 'credit',
                amount: 10,
                balance_after: 10,
                description: 'Mua token',
            },
        ],
    });
    return { solves: page('solves'), transactions: page('transactions') };
}

const settle = () => new Promise((resolve) => setImmediate(resolve));

test('history renders safe table cells and keeps pagination and filters independent', async () => {
    const previousDocument = globalThis.document;
    const previousFormData = globalThis.FormData;
    globalThis.document = { createElement: element };
    globalThis.FormData = class {
        constructor(form) {
            return form.values;
        }
    };
    try {
        const urls = [];
        const { load, get } = fixture(async (url) => {
            urls.push(url);
            return response(url);
        });
        await load();
        const cells = get('[data-captcha-history]').children[0].children;
        assert.equal(cells.length, 6);
        assert.equal(cells[1].textContent, '<img src=x onerror=alert(1)>');
        assert.equal(cells[1].children.length, 0);
        assert.equal(cells[2].children[0].textContent, 'Thành công');
        assert.equal(get('[data-captcha-solves-prev]').disabled, true);
        assert.equal(get('[data-captcha-solves-next]').disabled, false);
        get('[data-captcha-transactions-next]').handlers.click();
        await settle();
        get('[data-captcha-solves-next]').handlers.click();
        await settle();
        assert.equal(get('[data-captcha-solves-next]').disabled, true);
        const form = get('[data-captcha-filters="solves"]');
        form.values = [
            ['status', 'success'],
            ['source', 'api'],
            ['solves_search', '123'],
        ];
        form.handlers.submit({ preventDefault() {} });
        await settle();
        const filtered = new URL(urls.at(-1), 'https://example.test').searchParams;
        assert.equal(filtered.get('solves_page'), '1');
        assert.equal(filtered.get('transactions_page'), '2');
        assert.equal(filtered.get('status'), 'success');
        assert.equal(filtered.get('solves_search'), '123');
        form.handlers.reset();
        await settle();
        assert.equal(new URL(urls.at(-1), 'https://example.test').searchParams.has('status'), false);
        assert.equal(get('[data-captcha-solves-summary]').textContent, '1–20 / 21 bản ghi');
    } finally {
        globalThis.document = previousDocument;
        globalThis.FormData = previousFormData;
    }
});

test('history ignores stale responses and recovers after request errors', async () => {
    const previousDocument = globalThis.document;
    globalThis.document = { createElement: element };
    try {
        const pending = [];
        const { load, get, errors } = fixture((url) => new Promise((resolve, reject) => pending.push({ url, resolve, reject })));
        const first = load();
        const second = load();
        assert.equal(get('[data-captcha-history-load]').disabled, true);
        pending[1].resolve(response(pending[1].url, 'new'));
        await second;
        pending[0].resolve(response(pending[0].url, 'old'));
        await first;
        assert.equal(get('[data-captcha-history]').children[0].children[1].textContent, 'new');
        const third = load();
        pending[2].reject(new Error('Network failed'));
        await third;
        assert.deepEqual(errors, ['Network failed']);
        assert.equal(get('[data-captcha-solves-next]').disabled, true);
        assert.equal(get('[data-captcha-history-load]').disabled, false);
        const fourth = load();
        pending[3].resolve(response(pending[3].url));
        await fourth;
        assert.equal(get('[data-captcha-solves-next]').disabled, false);
    } finally {
        globalThis.document = previousDocument;
    }
});
