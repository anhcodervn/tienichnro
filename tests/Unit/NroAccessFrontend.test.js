import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeNroAccess } from '../../resources/js/nro-access.js';

function accessFixture(token = 'valid-token') {
    const original = globalThis.FormData;
    globalThis.FormData = class {
        get() {
            return token;
        }
    };
    const button = { disabled: false };
    const error = { hidden: true };
    const requests = [];
    let submit;
    let reloads = 0;
    let resets = 0;
    const form = {
        dataset: {},
        action: '/thong-bao-game/xac-minh',
        querySelector: (selector) => (selector === '[type="submit"]' ? button : error),
        addEventListener: (_, callback) => {
            submit = callback;
        },
    };
    initializeNroAccess(
        { querySelector: () => form },
        {
            post: (...args) => new Promise((resolve, reject) => requests.push({ args, resolve, reject })),
        },
        { location: { reload: () => reloads++ }, turnstile: { reset: () => resets++ } },
    );
    return {
        button,
        error,
        requests,
        submit: () => submit({ preventDefault() {} }),
        reloads: () => reloads,
        resets: () => resets,
        restore: () => {
            globalThis.FormData = original;
        },
    };
}

test('verification waits for a challenge and prevents duplicate submissions', async () => {
    const empty = accessFixture('');
    try {
        await empty.submit();
        assert.equal(empty.requests.length, 0);
        assert.equal(empty.error.hidden, false);
    } finally {
        empty.restore();
    }
    const fixture = accessFixture();
    try {
        const pending = fixture.submit();
        await fixture.submit();
        assert.equal(fixture.requests.length, 1);
        assert.equal(fixture.button.disabled, true);
        assert.equal(fixture.requests[0].args[2].headers.Accept, 'application/json');
        fixture.requests[0].resolve({});
        await pending;
        assert.equal(fixture.reloads(), 1);
        assert.equal(fixture.button.disabled, false);
    } finally {
        fixture.restore();
    }
});

test('verification failures show server errors safely and reset the challenge for retry', async () => {
    const fixture = accessFixture();
    try {
        const pending = fixture.submit();
        fixture.requests[0].reject({ response: { status: 422, data: { errors: { 'cf-turnstile-response': ['Expired token'] } } } });
        await pending;
        assert.equal(fixture.error.textContent, 'Expired token');
        assert.equal(fixture.error.hidden, false);
        assert.equal(fixture.resets(), 1);
        assert.equal(fixture.button.disabled, false);
        assert.equal(fixture.reloads(), 0);
        const retry = fixture.submit();
        fixture.requests[1].resolve({});
        await retry;
        assert.equal(fixture.reloads(), 1);
    } finally {
        fixture.restore();
    }
});
