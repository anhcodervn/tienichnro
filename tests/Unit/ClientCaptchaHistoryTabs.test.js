import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeCaptchaHistoryTabs } from '../../resources/js/client-captcha.js';

function fixture() {
    const tabs = ['solves', 'transactions'].map((name, index) => {
        const classes = new Set([index === 0 ? 'client-button' : 'client-button-secondary']);
        return {
            dataset: { captchaHistoryTab: name },
            attributes: { 'aria-selected': String(index === 0) },
            tabIndex: index === 0 ? 0 : -1,
            handlers: {},
            focused: false,
            classList: { toggle: (value, enabled) => (enabled ? classes.add(value) : classes.delete(value)) },
            setAttribute(key, value) {
                this.attributes[key] = value;
            },
            addEventListener(key, handler) {
                this.handlers[key] = handler;
            },
            focus() {
                this.focused = true;
            },
        };
    });
    const panels = ['solves', 'transactions'].map((name, index) => ({
        dataset: { captchaHistoryPanel: name },
        hidden: index !== 0,
        page: index + 2,
    }));
    initializeCaptchaHistoryTabs({
        querySelectorAll: (selector) => (selector === '[data-captcha-history-tab]' ? tabs : panels),
    });
    return { tabs, panels };
}

test('switching history tabs displays only the selected panel and preserves each page', () => {
    const { tabs, panels } = fixture();
    tabs[1].handlers.click();
    assert.deepEqual(
        panels.map((panel) => panel.hidden),
        [true, false],
    );
    assert.deepEqual(
        tabs.map((tab) => tab.attributes['aria-selected']),
        ['false', 'true'],
    );
    assert.deepEqual(
        tabs.map((tab) => tab.tabIndex),
        [-1, 0],
    );
    tabs[0].handlers.click();
    assert.deepEqual(
        panels.map((panel) => panel.hidden),
        [false, true],
    );
    assert.deepEqual(
        panels.map((panel) => panel.page),
        [2, 3],
    );
});

test('keyboard navigation wraps and supports Home and End without intercepting Tab', () => {
    const { tabs } = fixture();
    let prevented = 0;
    const press = (index, key) => tabs[index].handlers.keydown({ key, preventDefault: () => prevented++ });
    press(0, 'ArrowLeft');
    assert.equal(tabs[1].attributes['aria-selected'], 'true');
    assert.equal(tabs[1].focused, true);
    press(1, 'ArrowRight');
    assert.equal(tabs[0].attributes['aria-selected'], 'true');
    press(0, 'End');
    assert.equal(tabs[1].attributes['aria-selected'], 'true');
    press(1, 'Home');
    assert.equal(tabs[0].attributes['aria-selected'], 'true');
    press(0, 'Tab');
    assert.equal(prevented, 4);
});
