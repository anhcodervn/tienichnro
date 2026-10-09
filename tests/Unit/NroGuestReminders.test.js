import assert from 'node:assert/strict';
import test from 'node:test';
import { createNroGuestReminders } from '../../resources/js/nro-guest-reminders.js';

function fixture(storage = new Map(), member = false) {
    let time = 1000000;
    let stopped = 0;
    let reloads = 0;
    const dialogs = [];
    const destinations = [];
    const reminders = createNroGuestReminders({
        expiresAt: 1900000,
        loginUrl: '/dang-nhap',
        clock: () => time,
        alerts: {
            fire: async (options) => {
                dialogs.push(options);
                return { isConfirmed: false };
            },
        },
        browser: {
            localStorage: { getItem: (key) => storage.get(key), setItem: (key, value) => storage.set(key, value) },
            location: { reload: () => reloads++, assign: (url) => destinations.push(url) },
        },
        stopStream: () => stopped++,
        checkMember: async () => member,
    });
    return {
        reminders,
        dialogs,
        destinations,
        stopped: () => stopped,
        reloads: () => reloads,
        advance: async (minutes) => {
            time = 1000000 + minutes * 60000;
            await reminders.tick();
        },
    };
}

test('guests receive reminders at 5 10 and 15 minutes and the third stops SSE', async () => {
    const f = fixture();
    await f.advance(4);
    assert.equal(f.dialogs.length, 0);
    for (const minute of [5, 10, 15]) {
        await f.advance(minute);
        assert.equal(f.dialogs.length, minute / 5);
        assert.equal(f.dialogs.at(-1).text, 'Vui lòng đăng nhập để tiếp tục sử dụng.');
    }
    assert.equal(f.stopped(), 1);
    assert.equal(f.reminders.isExpired(), true);
    await f.advance(20);
    assert.equal(f.dialogs.length, 3);
});

test('refresh does not repeat previous reminders and inactive tabs show one current reminder', async () => {
    const storage = new Map();
    const first = fixture(storage);
    await first.advance(5);
    const refreshed = fixture(storage);
    await refreshed.advance(5);
    assert.equal(refreshed.dialogs.length, 0);
    await refreshed.advance(15);
    assert.equal(refreshed.dialogs.length, 1);
    assert.equal(refreshed.stopped(), 1);
    await refreshed.reminders.expire();
    assert.equal(refreshed.dialogs.length, 1);
});

test('login in another tab restores access without showing a login reminder', async () => {
    const f = fixture(new Map(), true);
    await f.advance(5);
    assert.equal(f.reloads(), 1);
    assert.equal(f.dialogs.length, 0);
    await f.advance(20);
    assert.equal(f.stopped(), 0);
});

test('open alerts never overlap and login confirmation uses the supplied login route', async () => {
    let resolve;
    let time = 300000;
    let calls = 0;
    let stopped = false;
    let navigated;
    const reminders = createNroGuestReminders({
        expiresAt: 900000,
        loginUrl: '/dang-nhap',
        clock: () => time,
        browser: {
            location: {
                assign: (url) => {
                    navigated = url;
                },
            },
        },
        checkMember: async () => false,
        stopStream: () => {
            stopped = true;
        },
        alerts: {
            fire: () => {
                calls++;
                return new Promise((done) => {
                    resolve = done;
                });
            },
        },
    });
    const pending = reminders.tick();
    await new Promise((done) => setImmediate(done));
    time = 900000;
    await reminders.tick();
    assert.equal(calls, 1);
    assert.equal(stopped, true);
    resolve({ isConfirmed: true });
    await pending;
    assert.equal(navigated, '/dang-nhap');
});
