import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeClientTheme } from '../../resources/js/client-theme.js';

function environment(stored = null, systemDark = false, blocked = false) {
    const listeners = {};
    const attrs = {};
    const classes = new Set();
    const button = {
        dataset: {},
        setAttribute: (key, value) => (attrs[key] = value),
        addEventListener: (key, callback) => (listeners[key] = callback),
    };
    const media = { matches: systemDark, addEventListener: (key, callback) => (listeners.media = callback) };
    const root = {
        querySelector: () => button,
        documentElement: {
            classList: { toggle: (key, active) => (active ? classes.add(key) : classes.delete(key)), contains: (key) => classes.has(key) },
        },
    };
    const browser = {
        matchMedia: () => media,
        localStorage: {
            getItem: () => {
                if (blocked) throw new Error('blocked');
                return stored;
            },
            setItem: (key, value) => {
                if (blocked) throw new Error('blocked');
                stored = value;
            },
        },
        addEventListener: (key, callback) => (listeners[key] = callback),
    };
    return { root, browser, listeners, attrs, media, dark: () => classes.has('dark'), stored: () => stored };
}

test('theme follows system until manual choice and retains it on reload', () => {
    const env = environment(null, true);
    initializeClientTheme(env.root, env.browser);
    assert.equal(env.dark(), true);
    assert.equal(env.attrs['aria-pressed'], 'true');
    env.listeners.click();
    assert.equal(env.dark(), false);
    assert.equal(env.stored(), 'light');
    env.listeners.media();
    assert.equal(env.dark(), false);
    const reload = environment(env.stored(), true);
    initializeClientTheme(reload.root, reload.browser);
    assert.equal(reload.dark(), false);
});

test('theme handles OS and cross-tab changes with storage blocked', () => {
    const env = environment(null, false, true);
    initializeClientTheme(env.root, env.browser);
    env.media.matches = true;
    env.listeners.media();
    assert.equal(env.dark(), true);
    env.listeners.click();
    assert.equal(env.dark(), false);
    env.listeners.storage({ key: 'unrelated', newValue: 'dark' });
    assert.equal(env.dark(), false);
    env.listeners.storage({ key: 'client-theme', newValue: 'dark' });
    assert.equal(env.dark(), true);
    env.media.matches = false;
    env.listeners.storage({ key: null, newValue: null });
    assert.equal(env.dark(), false);
});
