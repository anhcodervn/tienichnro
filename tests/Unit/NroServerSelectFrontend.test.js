import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeNroServerSelect } from '../../resources/js/nro-server-select.js';

function fixture(value = '') {
    const originalDocument = globalThis.document;
    const documentHandlers = {};
    const browserDocument = {
        activeElement: null,
        addEventListener: (type, listener) => (documentHandlers[type] = listener),
    };
    globalThis.document = browserDocument;
    function element(dataset = {}) {
        const classes = new Set();
        return {
            dataset,
            hidden: false,
            attributes: {},
            handlers: {},
            textContent: '',
            classList: {
                add: (...names) => names.forEach((name) => classes.add(name)),
                remove: (...names) => names.forEach((name) => classes.delete(name)),
                contains: (name) => classes.has(name),
            },
            setAttribute(name, content) {
                this.attributes[name] = content;
            },
            addEventListener(type, listener) {
                this.handlers[type] = listener;
            },
            dispatchEvent(event) {
                this.handlers[event.type]?.(event);
            },
            focus() {
                browserDocument.activeElement = this;
            },
        };
    }
    const select = element();
    select.value = value;
    const custom = element();
    custom.hidden = true;
    const trigger = element();
    const list = element();
    list.hidden = true;
    const label = element();
    const dot = element();
    const status = element();
    const options = [
        element({ value: '', name: 'Tất cả server', status: '' }),
        element({ value: '1', name: 'Vũ trụ 1', status: 'online' }),
        element({ value: '2', name: 'Vũ trụ 2', status: 'offline' }),
    ];
    const elements = {
        '[data-nro-server-native]': select,
        '[data-nro-server-custom]': custom,
        '[data-nro-server-trigger]': trigger,
        '[data-nro-server-list]': list,
        '[data-nro-server-value]': label,
        '[data-nro-server-dot]': dot,
        '[data-nro-server-status]': status,
    };
    const container = element();
    container.querySelector = (selector) => elements[selector];
    container.querySelectorAll = () => options;
    container.contains = (target) => Object.values(elements).includes(target) || options.includes(target);
    const synchronize = initializeNroServerSelect({ querySelector: () => container });
    return {
        select,
        custom,
        trigger,
        list,
        label,
        dot,
        status,
        options,
        container,
        synchronize,
        browserDocument,
        documentHandlers,
        restore: () => {
            globalThis.document = originalDocument;
        },
    };
}

test('custom selector shows the current server status and preserves native form value', () => {
    const ui = fixture('1');
    try {
        assert.equal(ui.select.hidden, true);
        assert.equal(ui.custom.hidden, false);
        assert.equal(ui.select.value, '1');
        assert.equal(ui.label.textContent, 'Vũ trụ 1');
        assert.equal(ui.status.textContent, 'Hoạt động');
        assert.equal(ui.dot.classList.contains('bg-emerald-500'), true);
        assert.equal(ui.options[1].attributes['aria-selected'], 'true');
        ui.trigger.handlers.click();
        assert.equal(ui.list.hidden, false);
        assert.equal(ui.browserDocument.activeElement, ui.options[1]);
        ui.options[2].handlers.click();
        assert.equal(ui.select.value, '2');
        assert.equal(ui.label.textContent, 'Vũ trụ 2');
        assert.equal(ui.status.textContent, 'Không hoạt động');
        assert.equal(ui.dot.classList.contains('bg-rose-500'), true);
        assert.equal(ui.list.hidden, true);
        assert.equal(ui.browserDocument.activeElement, ui.trigger);
    } finally {
        ui.restore();
    }
});

test('keyboard navigation supports arrows home end and escape without changing selection early', () => {
    const ui = fixture();
    const key = (name) => ({ key: name, preventDefault() {} });
    try {
        ui.trigger.handlers.keydown(key('ArrowDown'));
        assert.equal(ui.browserDocument.activeElement, ui.options[0]);
        ui.options[0].handlers.keydown(key('ArrowDown'));
        assert.equal(ui.browserDocument.activeElement, ui.options[1]);
        assert.equal(ui.select.value, '');
        ui.options[1].handlers.keydown(key('End'));
        assert.equal(ui.browserDocument.activeElement, ui.options[2]);
        ui.options[2].handlers.keydown(key('Home'));
        assert.equal(ui.browserDocument.activeElement, ui.options[0]);
        ui.options[0].handlers.keydown(key('ArrowUp'));
        assert.equal(ui.browserDocument.activeElement, ui.options[2]);
        ui.container.handlers.keydown(key('Escape'));
        assert.equal(ui.list.hidden, true);
        assert.equal(ui.trigger.attributes['aria-expanded'], 'false');
        assert.equal(ui.browserDocument.activeElement, ui.trigger);
    } finally {
        ui.restore();
    }
});

test('clicking outside or tabbing away closes the menu', () => {
    const ui = fixture();
    try {
        ui.trigger.handlers.click();
        ui.documentHandlers.pointerdown({ target: ui.options[1] });
        assert.equal(ui.list.hidden, false);
        ui.documentHandlers.pointerdown({ target: {} });
        assert.equal(ui.list.hidden, true);
        ui.trigger.handlers.click();
        ui.container.handlers.focusout({ relatedTarget: null });
        assert.equal(ui.list.hidden, true);
    } finally {
        ui.restore();
    }
});

test('history synchronization and choosing all servers reset label and status', () => {
    const ui = fixture('2');
    try {
        ui.select.value = '1';
        ui.synchronize();
        assert.equal(ui.label.textContent, 'Vũ trụ 1');
        ui.options[0].handlers.click();
        assert.equal(ui.select.value, '');
        assert.equal(ui.label.textContent, 'Tất cả server');
        assert.equal(ui.status.textContent, '');
        assert.equal(ui.dot.classList.contains('bg-slate-400'), true);
    } finally {
        ui.restore();
    }
});

test('pages without the server selector can initialize safely', () => {
    assert.equal(initializeNroServerSelect({ querySelector: () => null }), undefined);
});
