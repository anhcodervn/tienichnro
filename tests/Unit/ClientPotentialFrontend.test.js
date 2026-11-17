import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../../resources/js/client-potential.js', import.meta.url), 'utf8');
const { initializePotentialCalculator } = await import(`data:text/javascript,${encodeURIComponent(source.replace(/^import .*$/gm, ''))}`);
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
    const error = { hidden: true, textContent: '' };
    const empty = { hidden: false, textContent: '' };
    const values = Object.fromEntries(
        ['total', 'planet', 'level', 'hp', 'ki', 'attack', 'armor', 'critical'].map((key) => [key, { textContent: '' }]),
    );
    const output = {
        hidden: true,
        querySelector: (selector) => values[selector.match(/cost="(\w+)"/)?.[1] || selector.match(/potential-(\w+)\]/)[1]],
    };
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
        querySelector: (selector) => (selector === '[type="submit"]' ? button : selector === '[data-potential-error]' ? error : hint),
        reportValidity: () => true,
        addEventListener: (name, handler) => (handlers.submit = handler),
        setAttribute: (name, value) => (attributes[name] = value),
        removeAttribute: (name) => delete attributes[name],
    };
    const calls = [];
    const root = {
        querySelector: (selector) =>
            ({ '[data-potential-form]': form, '[data-potential-result]': output, '[data-potential-empty]': empty })[selector],
    };
    const http = {
        post: (...args) => {
            calls.push(args);
            return post(...args);
        },
    };
    initializePotentialCalculator(root, http);
    initializePotentialCalculator(root, http);
    return {
        fields,
        form,
        button,
        hint,
        attributes,
        calls,
        output,
        empty,
        error,
        values,
        handlers,
        submit: () => handlers.submit({ preventDefault() {} }),
    };
}

globalThis.FormData = class {
    constructor(form) {
        this.form = form;
    }
};

test('inline result includes all costs Vietnamese number separators total and level', async () => {
    const env = fixture(async () => ({ data: { status: true, data: result } }));
    await env.submit();
    assert.equal(env.values.total.textContent, '50.503.500');
    assert.equal(env.values.hp.textContent, '1.200');
    assert.equal(env.values.ki.textContent, '1.100');
    assert.equal(env.values.attack.textContent, '1.200');
    assert.equal(env.values.armor.textContent, '500.000');
    assert.equal(env.values.critical.textContent, '50.000.000');
    assert.equal(env.values.level.textContent, result.level);
    assert.equal(env.values.planet.textContent, result.planet);
    assert.equal(env.output.hidden, false);
    assert.equal(env.empty.hidden, true);
    assert.equal(env.error.hidden, true);
    const base = fixture(async () => ({ data: { status: true, data: { ...result, total: 0, level: null } } }));
    await base.submit();
    assert.equal(base.values.level.textContent, 'Chưa đạt Tân Binh');
    assert.equal(base.values.total.textContent, '0');
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
    assert.equal(env.output.hidden, true);
    assert.equal(env.empty.textContent, 'Đang tính tiềm năng…');
    resolve({ data: { status: true, data: result } });
    await pending;
    assert.equal(env.output.hidden, false);
    assert.equal(env.values.total.textContent, '50.503.500');
    assert.equal(env.button.disabled, false);
    assert.equal(env.attributes['aria-busy'], undefined);
});

for (const status of [422, 419, 429, 503, undefined]) {
    test(`request error ${status} retains inputs and permits another attempt`, async () => {
        const env = fixture(async () => {
            throw { response: { status, data: { message: '<img onerror=alert(1)>', errors: status === 422 ? { hp: ['HP không hợp lệ'] } : {} } } };
        });
        await env.submit();
        assert.equal(env.error.hidden, false);
        assert.equal(env.error.innerHTML, undefined);
        assert.equal(
            env.error.textContent,
            status === 422
                ? 'HP không hợp lệ'
                : status === 503
                  ? '<img onerror=alert(1)>'
                  : status === 419
                    ? 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'
                    : status === 429
                      ? 'Bạn thao tác quá nhiều lần. Vui lòng đợi một lúc rồi thử lại.'
                      : 'Không thể tính tiềm năng. Vui lòng kiểm tra kết nối rồi thử lại.',
        );
        assert.equal(env.output.hidden, true);
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

test('a failed recalculation hides old results and a later success clears the inline error', async () => {
    let attempts = 0;
    const env = fixture(async () => {
        attempts++;
        if (attempts === 2) throw new Error('offline');
        return { data: { status: true, data: result } };
    });
    await env.submit();
    assert.equal(env.output.hidden, false);
    await env.submit();
    assert.equal(env.output.hidden, true);
    assert.equal(env.error.hidden, false);
    await env.submit();
    assert.equal(env.output.hidden, false);
    assert.equal(env.error.hidden, true);
    assert.equal(env.error.textContent, '');
});
