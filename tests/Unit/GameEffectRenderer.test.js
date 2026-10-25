import assert from 'node:assert/strict';
import test from 'node:test';
import { drawEffectFrame, frameAtElapsed } from '../../resources/js/game-effect-renderer.js';

const data = {
    width: 146, height: 197, originX: 72, originY: 110, tickRate: 30,
    regions: [{ id: 9, x: 10, y: 20, width: 5, height: 6 }, { id: 2, x: 30, y: 40, width: 7, height: 8 }],
    frames: [{ pieces: [{ imageId: 2, dx: -72, dy: -110 }, { imageId: 9, dx: 0, dy: 0 }, { imageId: 2, dx: 3, dy: -4 }] }],
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
    for (const [time, frame] of [[0, 0], [99, 0], [100, 1], [199, 1], [200, 2], [300, 3], [399, 3], [400, 0], [500, 1]]) {
        assert.equal(frameAtElapsed(data, time), frame);
    }
});

test('refresh rate cannot change the animation frame at the same elapsed time', () => {
    for (const hz of [30, 60, 120, 144]) {
        for (let i = 0; i < hz; i++) frameAtElapsed(data, i * 1000 / hz);
        assert.equal(frameAtElapsed(data, 350), 3);
        assert.equal(frameAtElapsed(data, 1000), 2);
    }
    assert.equal(frameAtElapsed({ ...data, tickRate: 60 }, 100), 2);
});
