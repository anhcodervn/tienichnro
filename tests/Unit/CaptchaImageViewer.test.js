import assert from 'node:assert/strict';
import test from 'node:test';
import { moveImageDrag, startImageDrag, stopImageDrag, zoomImage } from '../../resources/js/pages/admin/captcha/image-viewer.js';

test('repeated zoom gestures stay within 50% and 800% and opposite gestures restore the size', () => {
    const state = { scale: 1, drag: null };
    zoomImage(state, 1.25);
    zoomImage(state, 0.8);
    assert.equal(state.scale, 1);
    for (let index = 0; index < 100; index++) zoomImage(state, 1.25);
    assert.equal(state.scale, 8);
    for (let index = 0; index < 100; index++) zoomImage(state, 0.8);
    assert.equal(state.scale, 0.5);
});

test('dragging a zoomed image pans from the initial scroll position and stops on release', () => {
    const state = { scale: 4, drag: null };
    let captured;
    const viewport = { scrollLeft: 250, scrollTop: 100, setPointerCapture: (id) => (captured = id) };
    startImageDrag(state, { button: 0, clientX: 100, clientY: 80, pointerId: 7 }, viewport);
    assert.equal(captured, 7);
    moveImageDrag(state, { clientX: 160, clientY: 50 }, viewport);
    assert.equal(viewport.scrollLeft, 190);
    assert.equal(viewport.scrollTop, 130);
    moveImageDrag(state, { clientX: 120, clientY: 100 }, viewport);
    assert.equal(viewport.scrollLeft, 230);
    assert.equal(viewport.scrollTop, 80);
    stopImageDrag(state);
    moveImageDrag(state, { clientX: 300, clientY: 300 }, viewport);
    assert.equal(viewport.scrollLeft, 230);
    assert.equal(viewport.scrollTop, 80);
    assert.equal(state.drag, null);
});

test('right click never starts a drag or changes the image position', () => {
    const state = { scale: 2, drag: null };
    const viewport = { scrollLeft: 20, scrollTop: 10, setPointerCapture: () => assert.fail('Right click captured pointer') };
    startImageDrag(state, { button: 2, clientX: 100, clientY: 80, pointerId: 1 }, viewport);
    moveImageDrag(state, { clientX: 200, clientY: 160 }, viewport);
    assert.equal(state.drag, null);
    assert.equal(viewport.scrollLeft, 20);
    assert.equal(viewport.scrollTop, 10);
});
