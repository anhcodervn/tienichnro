import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../../resources/js/client-potential.js', import.meta.url), 'utf8');
const { initializePotentialCalculator, potentialResultText } = await import(
    `data:text/javascript,${encodeURIComponent(source.replace(/^import .*$/gm, ''))}`
);
const result = {
    planet: 'Trái Đất',
    breakdown: { hp: 1200, ki: 1100, attack: 1200, armor: 500000, critical: 50000000 },
    total: 50503500,
    level: 'Siêu Nhân Cấp 2',
};

function fixture(post) {
    const handlers = {};
    const attributes = {};
    const button = { textContent: 'Kiểm tra', disabled: false };
    const hint = {};
    const fields = Object.fromEntries(['hp', 'ki', 'attack', 'armor', 'critical'].map((name) => [name, { value: '400' }]));
    fields.planet = {
        value: 'earth',
        selectedOptions: [{ dataset: { hp: '200', ki: '100', attack: '12' } }],
        addEventListener: (name, handler) => (handlers.change = handler),
    };
    const form = {
        action: '/tinh-tiem-nang',
        dataset: {},
        elements: { namedItem: (name) => fields[name] },
        querySelector: (selector) => (selector === '[type="submit"]' ? button : hint),
        reportValidity: () => true,
        addEventListener: (name, handler) => (handlers.submit = handler),
        setAttribute: (name, value) => (attributes[name] = value),
        removeAttribute: (name) => delete attributes[name],
    };
    const calls = [];
    const alerts = [];
    const root = { querySelector: () => form };
    const http = {
        post: (...args) => {
            calls.push(args);
            return post(...args);
        },
    };
    const notifications = { fire: async (options) => alerts.push(options) };
    initializePotentialCalculator(root, http, notifications);
    initializePotentialCalculator(root, http, notifications);
    return { fields, form, button, hint, attributes, calls, alerts, handlers, submit: () => handlers.submit({ preventDefault() {} }) };
}

globalThis.FormData = class {
    constructor(form) {
        this.form = form;
    }
};

test('result includes all costs, Vietnamese number separators, total and level', () => {
    const text = potentialResultText(result);
    assert.ok(text.includes('HP: 1.200 tiềm năng'));
    assert.ok(text.includes('Tổng tiềm năng đã nâng: 50.503.500'));
    assert.ok(text.includes(result.level));
    assert.ok(potentialResultText({ ...result, level: null }).includes('Chưa đạt Tân Binh'));
});

test('planet selection updates minimum inputs without overwriting entered stats', () => {
    const env = fixture(async () => ({ data: {} }));
    env.handlers.change();
    assert.equal(env.fields.hp.min, '200');
    assert.equal(env.fields.ki.min, '100');
    assert.equal(env.fields.hp.value, '400');
    env.fields.planet.value = 'namec';
    env.fields.planet.selectedOptions[0].dataset = { hp: '100', ki: '200', attack: '12' };
    env.handlers.change();
    assert.equal(env.fields.ki.min, '200');
    assert.ok(env.hint.textContent.includes('KI 200'));
});

test('Axios submission blocks duplicates, displays server results and unlocks the form', async () => {
    let resolve;
    const env = fixture(() => new Promise((done) => (resolve = done)));
    const pending = env.submit();
    await env.submit();
    assert.equal(env.calls.length, 1);
    assert.equal(env.calls[0][1].form, env.form);
    assert.equal(env.calls[0][2].headers.Accept, 'application/json');
    assert.equal(env.button.disabled, true);
    resolve({ data: { status: true, data: result } });
    await pending;
    assert.equal(env.alerts[0].text, potentialResultText(result));
    assert.equal(env.alerts[0].html, undefined);
    assert.equal(env.button.disabled, false);
    assert.equal(env.attributes['aria-busy'], undefined);
});

for (const status of [422, 419, 429, 503, undefined]) {
    test(`request error ${status} retains inputs and permits another attempt`, async () => {
        const env = fixture(async () => {
            throw { response: { status, data: { message: '<img onerror=alert(1)>', errors: status === 422 ? { hp: ['HP không hợp lệ'] } : {} } } };
        });
        await env.submit();
        assert.equal(env.alerts[0].icon, 'error');
        assert.equal(env.alerts[0].html, undefined);
        assert.equal(env.button.disabled, false);
        assert.equal(env.fields.hp.value, '400');
        await env.submit();
        assert.equal(env.calls.length, 2);
    });
}

test('invalid forms are not sent', async () => {
    const env = fixture(async () => ({ data: {} }));
    env.form.reportValidity = () => false;
    await env.submit();
    assert.equal(env.calls.length, 0);
});
