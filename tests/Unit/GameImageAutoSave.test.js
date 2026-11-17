import assert from 'node:assert/strict';
import test from 'node:test';
import { createAutoSave } from '../../resources/js/game-image-autosave.js';

function timers() {
    let now = 0;
    let id = 0;
    const jobs = new Map();
    const add = (callback, delay, interval = false) => {
        jobs.set(++id, { callback, time: now + delay, delay, interval });
        return id;
    };
    return {
        setTimeout: add,
        clearTimeout: (key) => jobs.delete(key),
        setInterval: (callback, delay) => add(callback, delay, true),
        clearInterval: (key) => jobs.delete(key),
        async tick(milliseconds) {
            const until = now + milliseconds;
            while (true) {
                const entry = [...jobs].filter(([, job]) => job.time <= until).sort((a, b) => a[1].time - b[1].time)[0];
                if (!entry) break;
                const [key, job] = entry;
                now = job.time;
                if (job.interval) job.time += job.delay;
                else jobs.delete(key);
                job.callback();
                await Promise.resolve();
                await Promise.resolve();
            }
            now = until;
        },
    };
}

test('autosave groups quick edits and saves continuous changes within five seconds', async () => {
    const clock = timers();
    let draft = 0;
    let saved = 0;
    const writes = [];
    const auto = createAutoSave({
        clock,
        isDirty: () => draft !== saved,
        canSave: () => true,
        save: async () => {
            saved = draft;
            writes.push(saved);
            return true;
        },
    });
    auto.start();
    draft = 1;
    auto.schedule();
    await clock.tick(500);
    draft = 2;
    auto.schedule();
    await clock.tick(700);
    assert.deepEqual(writes, [2]);
    for (let index = 0; index < 8; index++) {
        draft++;
        auto.schedule();
        await clock.tick(500);
    }
    assert.ok(writes.length >= 2);
    await clock.tick(1000);
    assert.equal(saved, draft);
    auto.stop();
});

test('autosave serializes requests preserves edits in flight and stops timers', async () => {
    const clock = timers();
    let draft = 1;
    let saved = 0;
    let finish;
    const writes = [];
    const auto = createAutoSave({
        clock,
        isDirty: () => draft !== saved,
        canSave: () => true,
        save: () => {
            const snapshot = draft;
            writes.push(snapshot);
            return new Promise((resolve) => {
                finish = () => {
                    saved = snapshot;
                    resolve(true);
                };
            });
        },
    });
    auto.start();
    await clock.tick(700);
    draft = 2;
    auto.schedule();
    await clock.tick(6000);
    assert.deepEqual(writes, [1]);
    finish();
    await Promise.resolve();
    await Promise.resolve();
    await clock.tick(700);
    assert.deepEqual(writes, [1, 2]);
    finish();
    await Promise.resolve();
    auto.stop();
    draft = 3;
    await clock.tick(10000);
    assert.deepEqual(writes, [1, 2]);
});

test('autosave skips gestures and retries failures on the next interval', async () => {
    const clock = timers();
    let editing = true;
    let calls = 0;
    const auto = createAutoSave({
        clock,
        isDirty: () => true,
        canSave: () => !editing,
        save: async () => {
            calls++;
            throw new Error('offline');
        },
    });
    auto.start();
    await clock.tick(5000);
    assert.equal(calls, 0);
    editing = false;
    auto.schedule();
    await clock.tick(700);
    assert.equal(calls, 1);
    await clock.tick(4300);
    assert.equal(calls, 2);
    auto.stop();
});
