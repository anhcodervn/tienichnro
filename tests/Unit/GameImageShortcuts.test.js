import assert from 'node:assert/strict';
import test from 'node:test';
import { createHistory } from '../../resources/js/game-image-editor-model.js';
import { handleStudioShortcut } from '../../resources/js/game-image-shortcuts.js';

function press(key, modifiers = {}, readOnly = false) {
    const calls = [];
    let prevented = false;
    const event = {
        key,
        preventDefault: () => {
            prevented = true;
        },
        ...modifiers,
    };
    const actions = new Proxy({}, { get: (_, action) => (value) => calls.push([action, value]) });
    handleStudioShortcut(event, actions, readOnly);
    return { calls, prevented };
}

test('CS6 layer and history shortcuts do not confuse duplicate with deselect', () => {
    for (const [key, modifiers, action] of [
        ['d', { ctrlKey: true }, 'deselect'],
        ['j', { ctrlKey: true }, 'duplicate'],
        ['t', { ctrlKey: true }, 'transform'],
        ['z', { ctrlKey: true }, 'toggleUndo'],
        ['z', { ctrlKey: true, altKey: true }, 'undo'],
        ['z', { metaKey: true, shiftKey: true }, 'redo'],
        ['s', { ctrlKey: true }, 'save'],
        ['s', { ctrlKey: true, shiftKey: true }, 'export'],
        ['a', { ctrlKey: true }, 'selectAll'],
    ]) {
        const result = press(key, modifiers);
        assert.equal(result.calls[0][0], action);
        assert.equal(result.prevented, true);
    }
});

test('tools navigation and pixel movement retain their modifiers', () => {
    assert.deepEqual(press(' ').calls, [['pan', undefined]]);
    assert.deepEqual(press('z').calls, [['tool', 'zoom']]);
    assert.deepEqual(press('t').calls, [['tool', 'text']]);
    assert.deepEqual(press('u', { shiftKey: true }).calls, [['shape', true]]);
    assert.deepEqual(press('ArrowLeft', { shiftKey: true }).calls, [['move', [-10, 0]]]);
    assert.deepEqual(press(']', { ctrlKey: true, shiftKey: true }).calls, [['reorder', 100]]);
    assert.deepEqual(press('}', { code: 'BracketRight', ctrlKey: true, shiftKey: true }).calls, [['reorder', 100]]);
    assert.equal(press('0', { ctrlKey: true }).calls[0][0], 'fit');
    assert.equal(press('1', { ctrlKey: true }).calls[0][0], 'actualSize');
});

test('typing composition browser shortcuts and held keys do not create unwanted layers', () => {
    for (const modifiers of [{ isComposing: true }, { target: { closest: () => ({}) } }, { ctrlKey: true }, { altKey: true }]) {
        assert.deepEqual(press('t', modifiers).calls, modifiers.ctrlKey ? [['transform', undefined]] : []);
    }
    assert.deepEqual(press('j', { ctrlKey: true, repeat: true }).calls, []);
    assert.equal(press('w', { ctrlKey: true }).prevented, false);
    assert.equal(press('ArrowRight', { repeat: true }).calls[0][0], 'move');
});

test('shared viewers can navigate but cannot change or save the project', () => {
    for (const key of ['j', 's', 't', 'z', 'd', 'a']) assert.deepEqual(press(key, { ctrlKey: true }, true).calls, []);
    assert.equal(press(' ', {}, true).calls[0][0], 'pan');
    assert.equal(press('z', {}, true).calls[0][0], 'tool');
    assert.equal(press('0', { ctrlKey: true }, true).calls[0][0], 'fit');
});

test('CS6 undo toggles the latest state and resets after editing or stepping history', () => {
    const history = createHistory('a');
    history.commit('b');
    history.commit('c');
    assert.equal(history.toggle(), 'b');
    assert.equal(history.toggle(), 'c');
    assert.equal(history.toggle(), 'b');
    history.commit('d');
    assert.equal(history.toggle(), 'b');
    assert.equal(history.toggle(), 'd');
    assert.equal(history.undo(), 'b');
    assert.equal(history.undo(), 'a');
    assert.equal(history.redo(), 'b');
});
