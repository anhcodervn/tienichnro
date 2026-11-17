import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { drawComposition, itemCrop, loadCaptureImage, PANEL_FILES, parseItemIds } from '../../resources/js/client-game-image.js';
import { captureRowsFromPixels } from '../../resources/js/game-image-capture.js';

function demoImage(file) {
    const png = readFileSync(new URL(`../../public/images/demo/capture/kztiger/${file}`, import.meta.url));
    return { file, naturalWidth: png.readUInt32BE(16), naturalHeight: png.readUInt32BE(20) };
}

function compose(ids, details) {
    const calls = [];
    const context = { clearRect() {}, fillRect() {}, fillText() {}, strokeRect() {}, drawImage: (...args) => calls.push(args) };
    const canvas = { getContext: () => context };
    const files = [...PANEL_FILES, 'equipment.png', ...(details ? ids.map((id) => `details/item-${String(id).padStart(4, '0')}.png`) : [])];
    const images = Object.fromEntries(files.map((file) => [file, demoImage(file)]));
    const dimensions = drawComposition(canvas, images, ids, details);
    return { calls, dimensions };
}

test('IDs preserve requested order, remove duplicates, and support an empty highlight list', () => {
    assert.deepEqual(parseItemIds('14, 1,005,11,14'), [14, 1, 5, 11]);
    assert.deepEqual(parseItemIds('  '), []);
    for (const value of ['0', '86', '999', '-1', '1.5', 'a', '1,,5', '1,', '1 5']) {
        assert.throws(() => parseItemIds(value));
    }
});

test('every bag and box crop stays inside its real capture and has a matching detail', () => {
    for (let id = 1; id <= 85; id++) {
        const crop = itemCrop(id);
        const source = demoImage(crop.file);
        assert.ok(crop.y + crop.height <= source.naturalHeight);
        assert.equal(crop.width, source.naturalWidth);
        assert.ok(demoImage(`details/item-${String(id).padStart(4, '0')}.png`).naturalWidth > 0);
    }
    assert.equal(itemCrop(35).file, 'bag.png');
    assert.equal(itemCrop(36).file, 'equipment.png');
    assert.deepEqual(itemCrop(47), { file: 'box.png', x: 0, y: 26, width: 440, height: 39 });
    assert.deepEqual(itemCrop(69), { file: 'box-84.png', x: 0, y: 26, width: 440, height: 39 });
    assert.equal(itemCrop(85).y, 620);
    assert.deepEqual(parseItemIds('47,68,69,85'), [47, 68, 69, 85]);
    assert.throws(() => itemCrop(86));
});

test('composition draws panels in requested order then only the selected detail images below them', () => {
    const { calls, dimensions } = compose([1, 5, 11, 14, 29, 45], true);
    assert.deepEqual(
        calls.slice(0, PANEL_FILES.length).map(([image]) => image.file),
        PANEL_FILES,
    );
    const panelBottom = Math.max(...calls.slice(0, PANEL_FILES.length).map(([image, , y]) => y + image.naturalHeight));
    for (const [index, id] of [1, 5, 11, 14, 29, 45].entries()) {
        const detail = calls[PANEL_FILES.length + index];
        assert.equal(detail[0].file, `details/item-${String(id).padStart(4, '0')}.png`);
        assert.ok(detail[2] > panelBottom);
        assert.equal(detail.length, 3);
        assert.ok(detail[2] + detail[0].naturalHeight <= dimensions.height);
    }
});

test('detail and row modes each draw selected items once and compact rows have no gaps', () => {
    const full = compose([1, 5, 11, 14], true);
    const compact = compose([1, 5, 11, 14], false);
    assert.equal(full.calls.length, PANEL_FILES.length + 4);
    assert.equal(compact.calls.length, PANEL_FILES.length + 4);
    assert.ok(compact.calls.every(([image]) => !image.file.startsWith('details/')));
    assert.ok(compact.dimensions.height <= full.dimensions.height);
    assert.equal(compact.calls[PANEL_FILES.length + 1][6] - compact.calls[PANEL_FILES.length][6], itemCrop(1).height);
    const panelsOnly = compose([], false);
    assert.equal(panelsOnly.calls.length, PANEL_FILES.length);
    assert.ok(panelsOnly.dimensions.height < compact.dimensions.height);
});

test('both NPC chests render their selected details and correctly cropped rows', () => {
    const ids = [47, 68, 69, 85];
    const full = compose(ids, true);
    assert.deepEqual(
        full.calls.slice(PANEL_FILES.length).map(([image]) => image.file),
        ids.map((id) => `details/item-${String(id).padStart(4, '0')}.png`),
    );
    const compact = compose(ids, false).calls.slice(PANEL_FILES.length);
    assert.deepEqual(
        compact.map(([image]) => image.file),
        ['box.png', 'box.png', 'box-84.png', 'box-84.png'],
    );
    for (let index = 0; index < ids.length; index++) {
        const crop = itemCrop(ids[index]);
        assert.deepEqual(compact[index].slice(1, 5), [0, crop.y, crop.width, crop.height]);
    }
});

test('row indexing detects variable row heights rather than cutting every 34 pixels', () => {
    const pixels = new Uint8ClampedArray(121 * 4);
    for (const start of [26, 61, 91]) {
        for (let y = start; y < start + 13; y++) pixels.set([57, 49, 37, 255], y * 4);
    }
    pixels.set([57, 49, 37, 255], 80 * 4);
    assert.deepEqual(captureRowsFromPixels(pixels, 121), [
        { y: 26, height: 35 },
        { y: 61, height: 30 },
        { y: 91, height: 30 },
    ]);
});

test('legacy captures retain the old chest numbering and crop boundaries', () => {
    const images = Object.fromEntries(
        ['bag.png', 'equipment.png', 'box.png', 'box-84.png'].map((file) => {
            const png = readFileSync(new URL(`../../public/images/demo/capture/nobikutex1/${file}`, import.meta.url));
            return [file, { naturalWidth: png.readUInt32BE(16), naturalHeight: png.readUInt32BE(20) }];
        }),
    );
    assert.deepEqual(itemCrop(29, images), { file: 'box.png', x: 0, y: 26, width: 440, height: 34 });
    assert.deepEqual(itemCrop(46, images), { file: 'box-84.png', x: 0, y: 26, width: 440, height: 34 });
});

test('image loading reports missing captures and resolves loaded images', async () => {
    const previousImage = globalThis.Image;
    try {
        globalThis.Image = class {
            set src(value) {
                if (value === 'missing.png') this.onerror();
                else this.onload();
            }
        };
        await assert.rejects(loadCaptureImage('missing.png'), /Không tải được ảnh demo/);
        assert.ok(await loadCaptureImage('bag.png'));
    } finally {
        globalThis.Image = previousImage;
    }
});
