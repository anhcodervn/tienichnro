import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../../resources/js/client-license-rotation.js', import.meta.url), 'utf8');
const { initializeLicenseRotation } = await import(`data:text/javascript,${encodeURIComponent(source.replace(/^import .*$/gm, ''))}`);

function environment(fire) {
    const button = { disabled: false };
    const confirmation = { value: '0' };
    const status = { textContent: '' };
    const listeners = [];
    let submissions = 0;
    const form = {
        dataset: {},
        querySelector: (selector) => (selector === 'button[type="submit"]' ? button : confirmation),
        addEventListener: (_, handler) => listeners.push(handler),
        submit: () => submissions++,
    };
    const root = { querySelectorAll: () => [form], querySelector: () => status };
    initializeLicenseRotation(root, { fire });
    initializeLicenseRotation(root, { fire });
    assert.equal(listeners.length, 1);
    return { button, confirmation, status, submissions: () => submissions, submit: () => listeners[0]({ preventDefault() {} }) };
}

test('rotation waits for SweetAlert confirmation before submitting the form', async () => {
    let resolve;
    let options;
    const env = environment((settings) => {
        options = settings;
        return new Promise((done) => (resolve = done));
    });
    const pending = env.submit();
    await env.submit();
    assert.equal(options.showCancelButton, true);
    assert.equal(options.confirmButtonText, 'Đổi key');
    assert.equal(options.cancelButtonText, 'Hủy');
    assert.equal(env.submissions(), 0);
    assert.equal(env.confirmation.value, '0');
    assert.equal(env.button.disabled, true);
    resolve({ isConfirmed: true });
    await pending;
    assert.equal(env.submissions(), 1);
    assert.equal(env.confirmation.value, '1');
    await env.submit();
    assert.equal(env.submissions(), 1);
});

for (const dismiss of ['cancel', 'backdrop', 'esc']) {
    test(`dismissing rotation with ${dismiss} leaves the key unchanged and allows retry`, async () => {
        const env = environment(async () => ({ isConfirmed: false, isDismissed: true, dismiss }));
        await env.submit();
        assert.equal(env.submissions(), 0);
        assert.equal(env.confirmation.value, '0');
        assert.equal(env.button.disabled, false);
        await env.submit();
        assert.equal(env.submissions(), 0);
    });
}

test('popup failure does not submit the form and restores the button', async () => {
    const env = environment(async () => {
        throw new Error('Popup failed');
    });
    await env.submit();
    assert.equal(env.submissions(), 0);
    assert.equal(env.confirmation.value, '0');
    assert.equal(env.button.disabled, false);
    assert.match(env.status.textContent, /Vui lòng thử lại/);
});
