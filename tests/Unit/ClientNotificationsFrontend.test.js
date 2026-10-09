import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../../resources/js/client-notifications.js', import.meta.url), 'utf8');
const { initializeClientNotifications } = await import(`data:text/javascript,${encodeURIComponent(source.replace(/^import .*$/gm, ''))}`);

const message = (type, texts) => ({
    dataset: { alertType: type, alertTitle: 'Thông báo' },
    querySelectorAll: () => texts.map((textContent) => ({ textContent })),
});

test('notifications show escaped text, collect validation errors and do not repeat', async () => {
    const elements = [message('error', [' Invalid email ', 'Invalid password', 'Invalid email', '<img onerror=alert(1)>'])];
    const calls = [];
    const root = { querySelectorAll: () => elements };
    const alerts = { fire: async (options) => calls.push(options) };
    await initializeClientNotifications(root, alerts);
    await initializeClientNotifications(root, alerts);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].icon, 'error');
    assert.equal(calls[0].text, 'Invalid email\nInvalid password\n<img onerror=alert(1)>');
    assert.equal(calls[0].html, undefined);
});

test('multiple notifications wait for the previous dialog to close and ignore empty messages', async () => {
    const elements = [message('success', ['Saved']), message('error', ['Failed']), message('info', [' '])];
    const calls = [];
    let closeFirst;
    const firstClosed = new Promise((resolve) => {
        closeFirst = resolve;
    });
    const done = initializeClientNotifications(
        { querySelectorAll: () => elements },
        {
            fire: async (options) => {
                calls.push(options);
                if (calls.length === 1) await firstClosed;
            },
        },
    );
    assert.equal(calls.length, 1);
    closeFirst();
    await done;
    assert.equal(calls.length, 2);
    assert.equal(calls[1].icon, 'error');
});
