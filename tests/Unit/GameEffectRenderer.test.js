import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeGameDataImages } from '../../resources/js/client-game-data.js';
import { drawEffectFrame, frameAtElapsed, mountEffect } from '../../resources/js/game-effect-renderer.js';

const data = {
    width: 146,
    height: 197,
    originX: 72,
    originY: 110,
    tickRate: 30,
    regions: [
        { id: 9, x: 10, y: 20, width: 5, height: 6 },
        { id: 2, x: 30, y: 40, width: 7, height: 8 },
    ],
    frames: [
        {
            pieces: [
                { imageId: 2, dx: -72, dy: -110 },
                { imageId: 9, dx: 0, dy: 0 },
                { imageId: 2, dx: 3, dy: -4 },
            ],
        },
    ],
    sequence: [0, 0, 0, 1, 1, 1, 2, 2, 2, 3, 3, 3],
};

test('atlas pieces use region IDs preserve duplicates and paint in array order with the shared origin', () => {
    const calls = [];
    const sheet = {};
    const context = { clearRect: (...args) => calls.push(['clear', ...args]), drawImage: (...args) => calls.push(args) };
    drawEffectFrame(context, sheet, data, 0);
    assert.equal(context.imageSmoothingEnabled, false);
    assert.deepEqual(calls, [
        ['clear', 0, 0, 146, 197],
        [sheet, 30, 40, 7, 8, 0, 0, 7, 8],
        [sheet, 10, 20, 5, 6, 72, 110, 5, 6],
        [sheet, 30, 40, 7, 8, 75, 106, 7, 8],
    ]);
});

test('repeated sequence entries last 100ms at 30 ticks per second and wrap at 400ms', () => {
    for (const [time, frame] of [
        [0, 0],
        [99, 0],
        [100, 1],
        [199, 1],
        [200, 2],
        [300, 3],
        [399, 3],
        [400, 0],
        [500, 1],
    ]) {
        assert.equal(frameAtElapsed(data, time), frame);
    }
});

test('refresh rate cannot change the animation frame at the same elapsed time', () => {
    for (const hz of [30, 60, 120, 144]) {
        for (let i = 0; i < hz; i++) frameAtElapsed(data, (i * 1000) / hz);
        assert.equal(frameAtElapsed(data, 350), 3);
        assert.equal(frameAtElapsed(data, 1000), 2);
    }
    assert.equal(frameAtElapsed({ ...data, tickRate: 60 }, 100), 2);
});

test('a broken icon tries the saved image once and repeated initialization does not duplicate listeners', () => {
    const handlers = [];
    const image = {
        dataset: { gameDataFallback: '/saved-image.png' },
        src: '/missing-icon.png',
        hidden: false,
        addEventListener: (event, handler) => {
            assert.equal(event, 'error');
            handlers.push(handler);
        },
    };
    const root = { querySelectorAll: () => [image] };
    initializeGameDataImages(root);
    initializeGameDataImages(root);
    assert.equal(handlers.length, 1);
    handlers[0]();
    assert.equal(image.src, '/saved-image.png');
    assert.equal(image.hidden, false);
    handlers[0]();
    assert.equal(image.hidden, true);
    assert.equal(image.dataset.gameDataImageFailed, 'true');
});

test('an icon that failed before JavaScript initialized also uses the saved image', () => {
    const image = {
        dataset: { gameDataFallback: '/saved-image.png' },
        src: '/cached-404.png',
        hidden: false,
        complete: true,
        naturalWidth: 0,
        addEventListener() {},
    };
    initializeGameDataImages({ querySelectorAll: () => [image] });
    assert.equal(image.src, '/saved-image.png');
    assert.equal(image.hidden, false);
});

test('animation failures show the saved PNG while successful first frames hide the fallback', async () => {
    const originals = new Map(
        ['Image', 'fetch', 'document', 'window', 'matchMedia'].map((key) => [key, Object.getOwnPropertyDescriptor(globalThis, key)]),
    );
    try {
        globalThis.Image = class {
            naturalWidth = 100;
            naturalHeight = 100;
            set src(value) {
                this.url = value;
                queueMicrotask(() => this.onload());
            }
        };
        globalThis.window = {};
        globalThis.document = { hidden: false, addEventListener() {}, removeEventListener() {} };
        globalThis.matchMedia = () => ({ matches: true });
        for (const ok of [false, true]) {
            globalThis.fetch = async () => ({ ok, json: async () => ({ ...data, sequence: [0] }) });
            const fallback = { hidden: !ok, dataset: {} };
            const status = { hidden: false, textContent: '' };
            const canvas = { hidden: true, getContext: () => ({ clearRect() {}, drawImage() {} }) };
            const element = {
                dataset: { gameEffect: JSON.stringify({ sheetUrl: '/sheet.png', dataUrl: `/animation-${ok}.json` }) },
                isConnected: true,
                querySelector: (selector) => ({ canvas, '[data-effect-status]': status, '[data-effect-fallback]': fallback })[selector],
            };
            await mountEffect(element);
            assert.equal(canvas.hidden, !ok);
            assert.equal(fallback.hidden, ok);
            assert.equal(status.hidden, ok);
        }
    } finally {
        for (const [key, descriptor] of originals) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor);
            else delete globalThis[key];
        }
    }
});
