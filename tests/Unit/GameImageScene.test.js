import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { PANEL_FILES } from '../../resources/js/client-game-image.js';
import { itemCrop } from '../../resources/js/game-image-capture.js';
import { applyLayers, validateProject } from '../../resources/js/game-image-editor-model.js';
import {
    canvasPoint,
    createScene,
    detailFile,
    drawScene,
    fitComposition,
    hitTest,
    limitCompositionSize,
    moveBlock,
    restoreHighlights,
    sceneSize,
    wrapText,
} from '../../resources/js/game-image-scene.js';

function captures(ids = []) {
    return Object.fromEntries(
        [...PANEL_FILES, 'equipment.png', ...ids.map(detailFile)].map((file) => {
            const png = readFileSync(new URL(`../../public/images/demo/capture/kztiger/${file}`, import.meta.url));
            return [file, { file, naturalWidth: png.readUInt32BE(16), naturalHeight: png.readUInt32BE(20) }];
        }),
    );
}

function surface() {
    const draws = [];
    const borders = [];
    const context = {
        clearRect() {},
        fillRect() {},
        fillText() {},
        drawImage: (...args) => draws.push(args),
        strokeRect: (...args) => borders.push(args),
    };
    return { canvas: { getContext: () => context }, draws, borders };
}

test('preview and export apply independent scales and custom canvas sizes without selection handles', () => {
    const calls = [];
    const context = {
        clearRect: (...args) => calls.push(['clear', ...args]),
        fillRect: (...args) => calls.push(['fill', ...args]),
        fillText() {},
        drawImage() {},
        save() {},
        restore() {},
        translate() {},
        rotate() {},
        scale: (...args) => calls.push(['scale', ...args]),
    };
    const canvas = { getContext: () => context };
    const blocks = applyLayers([{ key: 'bag', x: 20, y: 48, width: 100, height: 50, panel: true, pieces: [{ file: 'bag.png', x: 0, y: 0 }] }], {
        bag: { scale_x: 1.5, scale_y: 0.5 },
    });
    drawScene(canvas, { 'bag.png': {} }, blocks, { size: { width: 300, height: 200 } });
    assert.equal(canvas.width, 300);
    assert.equal(canvas.height, 200);
    assert.equal(context.imageSmoothingEnabled, true);
    assert.equal(context.imageSmoothingQuality, 'high');
    assert.ok(calls.some((call) => JSON.stringify(call) === JSON.stringify(['scale', 1.5, 0.5])));
    assert.ok(calls.some((call) => JSON.stringify(call) === JSON.stringify(['fill', 0, 0, 300, 200])));
    calls.length = 0;
    drawScene(canvas, { 'bag.png': {} }, blocks, { size: { width: 540, height: 440 }, backgroundSize: { width: 300, height: 200 } });
    assert.deepEqual(
        calls.find(([name]) => name === 'fill'),
        ['fill', 0, 0, 300, 200],
    );
});

test('an oversized capture is resized as one layout while preserving original item crop coordinates', () => {
    const rows = captures([1]);
    for (const image of Object.values(rows)) {
        image.naturalWidth *= 8;
        image.naturalHeight *= 8;
    }
    const fitted = createScene(rows, [1], false);
    const { width, height } = sceneSize(fitted);
    assert.ok(width <= 6000 && height <= 6000 && width * height <= 32000000);
    const bag = fitted.find((block) => block.key === 'bag');
    assert.ok(bag.width < rows['bag.png'].naturalWidth);
    assert.ok(Math.abs(bag.width / bag.height - rows['bag.png'].naturalWidth / rows['bag.png'].naturalHeight) < 1e-9);
    assert.equal(bag.pieces[0].resize, true);
    assert.deepEqual(fitted.at(-1).pieces[0].crop, itemCrop(1, rows));
    assert.ok(fitted.at(-1).pieces[0].width <= fitted.at(-1).width);
});

test('a custom composition scales content to fit the bounded export without changing layer positions', () => {
    const blocks = [{ key: 'bag', x: 20, y: 48, width: 6300, height: 7000, panel: true, pieces: [{ file: 'bag.png', x: 0, y: 0 }] }];
    const frame = fitComposition(blocks, { width: 8000, height: 8000 });
    assert.deepEqual(frame.size, limitCompositionSize({ width: 8000, height: 8000 }));
    assert.ok(frame.size.width <= 6000 && frame.size.height <= 6000 && frame.size.width * frame.size.height <= 32000000);
    assert.ok(frame.scale < 1 && (blocks[0].x + blocks[0].width) * frame.scale <= frame.size.width);
    assert.ok((blocks[0].y + blocks[0].height) * frame.scale <= frame.size.height);
    const { canvas } = surface();
    const calls = [];
    const context = canvas.getContext();
    Object.assign(context, { save() {}, restore() {}, translate() {}, scale: (...args) => calls.push(args) });
    drawScene(canvas, { 'bag.png': {} }, blocks, { size: { width: 8000, height: 8000 } });
    assert.deepEqual({ width: canvas.width, height: canvas.height }, frame.size);
    assert.deepEqual(calls, [[frame.scale, frame.scale]]);
    assert.equal(blocks[0].x, 20);
});

test('default layout omits tools in preview and export without leaving a gap', () => {
    const images = captures();
    const blocks = createScene(images, [], true);
    assert.deepEqual(
        blocks.map((block) => block.key),
        [
            'panel-quest',
            'panel-inventory',
            'panel-skills',
            'panel-pet',
            'bag',
            'box',
            'box-84',
            'npc-4-magic-tree',
            'npc-50-egg',
            'panel-amulets',
            'panel-radar',
        ],
    );
    assert.ok(!PANEL_FILES.includes('panel-tools.png'));
    const pet = blocks.find((block) => block.key === 'panel-pet');
    const skills = blocks.find((block) => block.key === 'panel-skills');
    const bag = blocks.find((block) => block.key === 'bag');
    const box = blocks.find((block) => block.key === 'box');
    assert.equal(bag.label, 'Hành trang');
    assert.equal(box.label, 'Rương ID 3');
    assert.equal(blocks.find((block) => block.key === 'box-84').label, 'Rương ID 84');
    assert.equal(pet.label, 'Đệ tử');
    assert.equal(pet.x, skills.x + skills.width);
    assert.equal(bag.x, pet.x + pet.width);
    assert.equal(box.x, bag.x + bag.width);
    const output = surface();
    drawScene(output.canvas, images, blocks);
    assert.deepEqual(
        output.draws.map(([image]) => image.file),
        PANEL_FILES,
    );
});

test('saved tools positions do not restore the removed panel', () => {
    const images = captures();
    const positions = { 'panel-tools': { x: 3000, y: 5000 }, 'panel-pet': { x: 100, y: 200 } };
    const blocks = createScene(images, [], true, positions);
    assert.ok(!blocks.some((block) => block.key === 'panel-tools'));
    assert.equal(blocks.find((block) => block.key === 'panel-pet').x, 100);
    const output = surface();
    drawScene(output.canvas, images, blocks);
    assert.ok(!output.draws.some(([image]) => image.file === 'panel-tools.png'));
    assert.deepEqual(sceneSize(blocks), sceneSize(createScene(images, [], true, { 'panel-pet': positions['panel-pet'] })));
});

test('scene retains default panel order and uses saved positions after reloading', () => {
    const images = captures([1, 29]);
    const saved = JSON.parse(JSON.stringify({ bag: { x: 900, y: 160 }, featured: { x: 50, y: 1000 } }));
    const blocks = createScene(images, [1, 29], true, saved);
    assert.deepEqual(
        blocks.map((block) => block.key),
        [...PANEL_FILES.map((file) => file.replace('.png', '')), 'featured'],
    );
    assert.equal(blocks.find((block) => block.key === 'bag').x, 900);
    assert.equal(blocks.find((block) => block.key === 'bag').y, 160);
    const featured = blocks.at(-1);
    assert.equal(featured.x, 50);
    assert.equal(featured.y, 1000);
    assert.equal(featured.pieces[0].file, detailFile(1));
    assert.equal(featured.pieces[1].file, detailFile(29));
    assert.equal(featured.pieces[0].crop, undefined);
    assert.ok(sceneSize(blocks).height >= featured.y + featured.height);
});

test('default highlight groups form a separate row below all panels', () => {
    const groups = [
        { id: 'featured-1', item_ids: [1, 5], show_details: true, columns: 2 },
        { id: 'featured-2', item_ids: [11], show_details: true, columns: 1 },
    ];
    const blocks = createScene(captures([1, 5, 11]), [], true, {}, groups);
    const panelBottom = Math.max(...blocks.slice(0, PANEL_FILES.length).map((block) => block.y + block.height));
    const [first, second] = blocks.slice(PANEL_FILES.length);
    assert.equal(first.x, 20);
    assert.equal(first.y, panelBottom + 12);
    assert.equal(second.y, first.y);
    assert.equal(second.x, first.x + first.width + 12);
});

test('custom group names survive restoration and appear in PNG titles within the block width', () => {
    const groups = [
        { id: 'featured-1', name: '  Trang bị hiếm  ', item_ids: [1], show_details: true, columns: 1 },
        { id: 'featured-2', name: '   ', item_ids: [5], show_details: true, columns: 1 },
    ];
    const restored = restoreHighlights({ groups, positions: {} });
    assert.equal(restored.groups[0].name, '  Trang bị hiếm  ');
    const blocks = createScene(captures([1, 5]), [], true, restored.positions, restored.groups);
    assert.equal(blocks.at(-2).label, 'Trang bị hiếm');
    assert.equal(blocks.at(-1).label, 'Khối nổi bật 2');
    const titles = [];
    const output = surface();
    const context = output.canvas.getContext();
    context.fillText = (...args) => titles.push(args);
    drawScene(output.canvas, captures([1, 5]), blocks);
    assert.deepEqual(
        titles.find(([text]) => text === 'Trang bị hiếm'),
        ['Trang bị hiếm', blocks.at(-2).x, blocks.at(-2).y + 16, 186],
    );
});

test('mouse and touch coordinates map correctly at mobile and desktop zoom levels', () => {
    for (const scale of [0.1, 0.25, 0.5, 1, 1.5]) {
        const rect = { left: -80, top: 120, width: 2400 * scale, height: 1000 * scale };
        const point = canvasPoint({ clientX: rect.left + 100 * scale, clientY: rect.top + 200 * scale }, rect, { width: 2400, height: 1000 });
        assert.equal(point.x, 100);
        assert.equal(point.y, 200);
    }
});

test('hit testing chooses the top visible block when panels overlap', () => {
    const blocks = createScene(captures(), [], false, { bag: { x: 20, y: 48 }, box: { x: 20, y: 48 } });
    assert.equal(hitTest(blocks, { x: 30, y: 80 }).key, 'box');
    assert.equal(hitTest(blocks, { x: 0, y: 0 }), undefined);
});

test('dragging rounds and clamps positions to the database validation bounds', () => {
    assert.deepEqual(moveBlock({ x: 100, y: 200 }, { x: 12.6, y: -50.1 }), { x: 113, y: 150 });
    assert.deepEqual(moveBlock({ x: 100, y: 200 }, { x: -500, y: -500 }), { x: 0, y: 48 });
    assert.deepEqual(moveBlock({ x: 5990, y: 5990 }, { x: 100, y: 100 }), { x: 6000, y: 6000 });
});

test('preview and PNG export draw the same moved content while export omits selection borders', () => {
    const images = captures([5, 11]);
    const blocks = createScene(images, [5, 11], true, { bag: { x: 200, y: 900 } });
    const preview = surface();
    const exported = surface();
    drawScene(preview.canvas, images, blocks, { selected: 'bag', size: { width: 4000, height: 2400 } });
    drawScene(exported.canvas, images, blocks);
    assert.deepEqual(preview.draws, exported.draws);
    assert.equal(preview.borders.length, 2);
    assert.equal(exported.borders.length, 1);
    assert.deepEqual(preview.draws.find(([image]) => image.file === 'bag.png').slice(1), [200, 900]);
    assert.deepEqual({ width: exported.canvas.width, height: exported.canvas.height }, sceneSize(blocks));
});

test('detail mode uses only detail images while row mode retains compact crops at the saved position', () => {
    const images = captures([1, 14, 45]);
    const position = { featured: { x: 600, y: 120 } };
    const full = createScene(images, [1, 14, 45], true, position).at(-1);
    const compact = createScene(images, [1, 14, 45], false, position).at(-1);
    assert.equal(full.pieces.length, 3);
    assert.equal(compact.pieces.length, 3);
    assert.equal(compact.x, 600);
    assert.equal(compact.y, 120);
    assert.ok(compact.height < full.height);
    assert.ok(full.pieces.every((piece) => piece.file.startsWith('details/')));
    assert.ok(compact.pieces.every((piece) => piece.crop));
});

test('detail images touch on both axes without overlapping or padded item rows', () => {
    const ids = [1, 5, 11, 14, 29, 45];
    const featured = createScene(captures(ids), ids, true).at(-1);
    const columns = [...new Set(featured.pieces.map((piece) => piece.x))];
    assert.deepEqual(columns, [0, 186]);
    for (const x of columns) {
        const pieces = featured.pieces.filter((piece) => piece.x === x);
        assert.equal(pieces[0].y, 24);
        for (let index = 1; index < pieces.length; index++) {
            assert.equal(pieces[index].y, pieces[index - 1].y + pieces[index - 1].height);
        }
    }
    assert.equal(featured.width, 372);
});

test('multiple highlight groups keep independent item lists columns and moved positions', () => {
    const groups = [
        { id: 'featured-1', item_ids: [1, 5, 11], show_details: true, columns: 3 },
        { id: 'featured-2', item_ids: [1, 29], show_details: true, columns: 1 },
    ];
    const blocks = createScene(captures([1, 5, 11, 29]), [], true, { 'featured-1': { x: 50, y: 900 }, 'featured-2': { x: 700, y: 1000 } }, groups);
    const first = blocks.at(-2);
    const second = blocks.at(-1);
    assert.equal(first.key, 'featured-1');
    assert.equal(second.key, 'featured-2');
    assert.deepEqual(
        first.pieces.map((piece) => piece.file),
        [detailFile(1), detailFile(5), detailFile(11)],
    );
    assert.equal(first.width, 558);
    assert.equal(second.width, 186);
    assert.equal(first.x, 50);
    assert.equal(second.x, 700);
    assert.equal(second.y, 1000);
    const output = surface();
    drawScene(output.canvas, captures([1, 5, 11, 29]), blocks);
    assert.equal(output.draws.length, PANEL_FILES.length + 5);
});

test('restoring old saved layouts migrates the highlight position without losing preferences', () => {
    const restored = restoreHighlights({
        item_ids: [11, 14],
        show_details: false,
        positions: { featured: { x: 600, y: 900 }, bag: { x: 20, y: 48 } },
    });
    assert.deepEqual(restored.groups, [{ id: 'featured-1', item_ids: [11, 14], show_details: false, columns: 2 }]);
    assert.deepEqual(restored.positions['featured-1'], { x: 600, y: 900 });
    assert.equal(restored.positions.featured, undefined);
    const empty = restoreHighlights({ groups: [], item_ids: [1], positions: {} });
    assert.deepEqual(empty.groups, []);
    assert.equal(createScene(captures(), [], true, {}, []).length, PANEL_FILES.length);
});

test('all featured items fit into the exported image even when moved below panels', () => {
    const ids = [...Array.from({ length: 22 }, (_, i) => i + 1), ...Array.from({ length: 18 }, (_, i) => i + 29)];
    const blocks = createScene(captures(ids), ids, true, { featured: { x: 3000, y: 5000 } });
    const size = sceneSize(blocks);
    for (const block of blocks) {
        for (const piece of block.pieces) {
            assert.ok(block.x + piece.x + piece.width < size.width);
            assert.ok(block.y + piece.y + piece.height < size.height);
        }
    }
});

test('new captures render on a white background with chest 84 and its item detail', () => {
    const images = captures([46]);
    const blocks = createScene(images, [46], true);
    const output = surface();
    const context = output.canvas.getContext();
    const backgrounds = [];
    context.fillRect = (...args) => backgrounds.push({ color: context.fillStyle, args });
    drawScene(output.canvas, images, blocks);
    assert.equal(backgrounds[0].color, '#ffffff');
    assert.deepEqual(backgrounds[0].args, [0, 0, output.canvas.width, output.canvas.height]);
    assert.ok(output.draws.some(([image]) => image.file === 'box-84.png'));
    assert.ok(output.draws.some(([image]) => image.file === detailFile(46)));
});

test('image title includes the character name and tienichnro.com', () => {
    const images = captures();
    const blocks = createScene(images, [], true);
    const output = surface();
    const titles = [];
    output.canvas.getContext().fillText = (text) => titles.push(text);
    drawScene(output.canvas, images, blocks);
    assert.equal(titles[0], 'NRO - kztiger - tienichnro.com');
    titles.length = 0;
    drawScene(output.canvas, images, blocks, { cname: '  Songoku  ' });
    assert.equal(titles[0], 'NRO - Songoku - tienichnro.com');
});

test('text blocks preserve line breaks and wrap Unicode content without losing characters', () => {
    assert.deepEqual(wrapText('Xin chào\r\nNRO\n', 360, 24), ['Xin chào', 'NRO', '']);
    const content = 'Ngọc Rồng 🐉 Việt Nam';
    const lines = wrapText(content, 120, 24);
    assert.ok(lines.length > 1);
    assert.equal(lines.join(''), content);
    assert.deepEqual(wrapText('', 360, 24), ['']);
});

test('text blocks render actual content and styling in the PNG and support independent saved positions', () => {
    const texts = [
        { id: 'text-1', content: 'Bán tài khoản\nLiên hệ: demo', font_size: 24, color: '#ff0000', bold: true, width: 360 },
        { id: 'text-2', content: '<b>Chữ thường</b>', font_size: 18, color: '#123456', bold: false, width: 300 },
    ];
    const images = captures();
    const blocks = createScene(images, [], true, { 'text-1': { x: 200, y: 1000 }, 'text-2': { x: 700, y: 1200 } }, [], texts);
    const first = blocks.at(-2);
    const second = blocks.at(-1);
    assert.equal(first.x, 200);
    assert.equal(first.y, 1000);
    assert.equal(second.x, 700);
    assert.equal(hitTest(blocks, { x: 210, y: 1010 }).key, 'text-1');
    assert.ok(sceneSize(blocks).height >= second.y + second.height);
    const output = surface();
    const context = output.canvas.getContext();
    const rendered = [];
    context.fillText = (value, x, y, width) => rendered.push({ value, x, y, width, font: context.font, color: context.fillStyle });
    drawScene(output.canvas, images, blocks);
    assert.ok(
        rendered.some((line) => line.value === 'Bán tài khoản' && line.color === '#ff0000' && line.font === 'bold 24px monospace' && line.x === 212),
    );
    assert.ok(rendered.some((line) => line.value === 'Liên hệ: demo'));
    assert.ok(rendered.some((line) => line.value === '<b>Chữ thường</b>' && line.font === '18px monospace'));
    assert.ok(!rendered.some((line) => line.value === 'Text 1'));
    assert.equal(output.draws.length, PANEL_FILES.length);
    assert.equal(createScene(images, [], true, {}, [], []).length, PANEL_FILES.length);
});

test('panels touch without label spacers and named highlights keep red frames in export', () => {
    const images = captures([1, 5]);
    const groups = [
        { id: 'featured-1', name: 'First', item_ids: [1], show_details: true, columns: 1 },
        { id: 'featured-2', name: 'Second', item_ids: [5], show_details: true, columns: 1 },
    ];
    const blocks = createScene(images, [], true, {}, groups);
    const panels = blocks.slice(0, PANEL_FILES.length);
    panels.forEach((panel, index) => {
        assert.equal(panel.pieces[0].y, 0);
        assert.equal(panel.height, images[PANEL_FILES[index]].naturalHeight);
        if (index && index !== 7) assert.equal(panel.x, panels[index - 1].x + panels[index - 1].width);
    });
    const output = surface();
    const context = output.canvas.getContext();
    const borders = [],
        titles = [];
    context.strokeRect = (...rect) => borders.push({ rect, color: context.strokeStyle, width: context.lineWidth });
    context.fillText = (text, x, y) => titles.push({ text, x, y, color: context.fillStyle });
    drawScene(output.canvas, images, blocks);
    assert.equal(borders.length, 2);
    for (const block of blocks.slice(PANEL_FILES.length)) {
        assert.ok(
            borders.some(
                ({ rect, color, width }) =>
                    color === '#dc2626' &&
                    width === 2 &&
                    rect[0] === block.x + 1 &&
                    rect[1] === block.y + 1 &&
                    rect[2] === block.width - 2 &&
                    rect[3] === block.height - 2,
            ),
        );
        assert.ok(titles.some(({ text, y, color }) => text === block.label && color === '#dc2626' && y < block.y + block.pieces[0].y));
    }
});

test('capture panels form two touching rows and chests stay side by side', () => {
    const blocks = createScene(captures(), [], true);
    const rowOne = blocks.slice(0, 7);
    const rowTwo = blocks.slice(7);
    const bottom = Math.max(...rowOne.map((block) => block.y + block.height));
    assert.ok(rowOne.every((block) => block.y === 48));
    assert.ok(rowTwo.every((block) => block.y === bottom));
    assert.equal(rowTwo[0].x, 20);
    assert.equal(rowOne[6].x, rowOne[5].x + rowOne[5].width);
    for (let i = 1; i < rowTwo.length; i++) assert.equal(rowTwo[i].x, rowTwo[i - 1].x + rowTwo[i - 1].width);
    assert.deepEqual(restoreHighlights(null).groups, []);
});

test('older captures without egg amulets or radar remain usable', () => {
    const images = captures();
    for (const file of ['npc-50-egg.png', 'panel-amulets.png', 'panel-radar.png']) delete images[file];
    const blocks = createScene(images, [], true);
    assert.equal(blocks.length, 8);
    assert.equal(blocks.at(-1).key, 'npc-4-magic-tree');
    assert.ok(sceneSize(blocks).height > 48);
});

test('pet and magic tree render as editable panels and restore independent positions', () => {
    const images = captures();
    const positions = { 'panel-pet': { x: 60, y: 900 }, 'npc-4-magic-tree': { x: 300, y: 1000 } };
    const blocks = createScene(images, [], true, positions);
    const output = surface();
    drawScene(output.canvas, images, blocks);
    for (const [key, position] of Object.entries(positions)) {
        const block = blocks.find((block) => block.key === key);
        assert.equal(block.x, position.x);
        assert.equal(block.y, position.y);
        assert.equal(block.width, images[`${key}.png`].naturalWidth);
        assert.deepEqual(output.draws.find(([image]) => image.file === `${key}.png`).slice(1), [position.x, position.y]);
    }
});

test('layers can move across the full 6000px background and imported projects preserve those positions', () => {
    assert.deepEqual(moveBlock({ x: 3000, y: 5000 }, { x: 1600, y: 500 }), { x: 4600, y: 5500 });
    const layout = { item_ids: [], show_details: true, positions: { bag: { x: 6000, y: 6000 } } };
    assert.equal(validateProject(layout), layout);
    assert.throws(() => validateProject({ ...layout, positions: { bag: { x: 6001, y: 6000 } } }));
});
