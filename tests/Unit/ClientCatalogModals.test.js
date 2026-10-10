import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeClientCatalogModals } from '../../resources/js/client-catalog-modals.js';

class Element {
    listeners = {};
    addEventListener(name, listener) {
        (this.listeners[name] ??= []).push(listener);
    }
    dispatch(name, event = {}) {
        let prevented = false;
        for (const listener of this.listeners[name] ?? []) {
            listener({
                target: this,
                currentTarget: this,
                preventDefault: () => {
                    prevented = true;
                },
                ...event,
            });
        }
        return prevented;
    }
    focus() {
        this.focused = true;
    }
}

class Dialog extends Element {
    open = false;
    closeButton = new Element();
    showModal() {
        this.open = true;
    }
    close() {
        this.open = false;
        this.dispatch('close');
    }
    querySelector() {
        return this.closeButton;
    }
    getBoundingClientRect() {
        return { left: 10, right: 100, top: 10, bottom: 100 };
    }
}

globalThis.HTMLElement = Element;
globalThis.HTMLDialogElement = Dialog;

function fixture() {
    const dialogs = { tools: new Dialog(), services: new Dialog() };
    const triggers = { tools: [new Element(), new Element(), new Element()], services: [new Element(), new Element(), new Element()] };
    const menu = { open: true };
    const root = {
        documentElement: { style: { overflow: 'auto' } },
        querySelector: (selector) => dialogs[selector.includes('services') ? 'services' : 'tools'],
        querySelectorAll: (selector) => (selector.startsWith('details') ? [menu] : triggers[selector.includes('services') ? 'services' : 'tools']),
    };
    initializeClientCatalogModals(root);
    return { dialogs, triggers, menu, root };
}

test('each desktop and mobile service trigger opens its own modal and restores focus and scrolling', () => {
    const { dialogs, triggers, menu, root } = fixture();
    for (const trigger of triggers.services) {
        assert.equal(trigger.dispatch('click'), true);
        assert.equal(dialogs.services.open, true);
        assert.equal(dialogs.tools.open, false);
        assert.equal(menu.open, false);
        assert.equal(root.documentElement.style.overflow, 'hidden');
        dialogs.services.closeButton.dispatch('click');
        assert.equal(dialogs.services.open, false);
        assert.equal(root.documentElement.style.overflow, 'auto');
        assert.equal(trigger.focused, true);
    }
});

test('keyboard and backdrop closing work for both catalogs without closing on content clicks', () => {
    const { dialogs, triggers, root } = fixture();
    for (const kind of ['services', 'tools']) {
        assert.equal(triggers[kind][0].dispatch('keydown', { key: 'ArrowDown' }), false);
        assert.equal(dialogs[kind].open, false);
        assert.equal(triggers[kind][0].dispatch('keydown', { key: ' ' }), true);
        triggers[kind][1].dispatch('click');
        dialogs[kind].dispatch('click', { clientX: 50, clientY: 50 });
        assert.equal(dialogs[kind].open, true);
        dialogs[kind].dispatch('click', { target: new Element(), clientX: 0, clientY: 0 });
        assert.equal(dialogs[kind].open, true);
        dialogs[kind].dispatch('click', { clientX: 0, clientY: 0 });
        assert.equal(dialogs[kind].open, false);
        assert.equal(root.documentElement.style.overflow, 'auto');
        assert.equal(triggers[kind][0].focused, true);
        assert.equal(triggers[kind][1].focused, undefined);
    }
});
