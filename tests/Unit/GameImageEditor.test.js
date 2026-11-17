import assert from 'node:assert/strict';
import test from 'node:test';
import {
    applyLayers,
    createHistory,
    reorderLayer,
    RESIZE_HANDLES,
    resizeLayer,
    rotatedBounds,
    validateProject,
} from '../../resources/js/game-image-editor-model.js';

test('layer controls preserve source dimensions and apply stacking', () => {
    const blocks = applyLayers(
        [
            { key: 'bag', width: 100, height: 50 },
            { key: 'box', width: 80, height: 40 },
        ],
        { bag: { scale: 2, opacity: 0.5, name: 'Main' } },
        ['box'],
    );
    assert.deepEqual(
        blocks.map((b) => b.key),
        ['box', 'bag'],
    );
    assert.equal(blocks[1].width, 200);
    assert.equal(blocks[1].originalWidth, 100);
    assert.equal(blocks[1].label, 'Main');
    assert.equal(blocks[1].opacity, 0.5);
});
test('undo redo and branching retain coherent history', () => {
    const history = createHistory('a', 3);
    history.commit('b');
    history.commit('c');
    assert.equal(history.undo(), 'b');
    assert.equal(history.redo(), 'c');
    history.undo();
    history.commit('d');
    assert.equal(history.canRedo, false);
    history.commit('e');
    history.undo();
    assert.equal(history.undo(), 'b');
    assert.equal(history.canUndo, false);
});

test('layer drag order matches the reversed layer list and preserves all layers', () => {
    const keys = ['bag', 'box', 'panel-pet', 'panel-radar'];
    assert.deepEqual(reorderLayer(keys, 'bag', 'panel-radar', 'before'), ['box', 'panel-pet', 'panel-radar', 'bag']);
    assert.deepEqual(reorderLayer(keys, 'panel-radar', 'bag', 'after'), ['panel-radar', 'bag', 'box', 'panel-pet']);
    assert.deepEqual(reorderLayer(keys, 'box', 'panel-pet', 'after'), keys);
    assert.equal(reorderLayer(keys, 'bag', 'bag'), keys);
    assert.equal(reorderLayer(keys, 'missing', 'box'), keys);
    assert.deepEqual(keys, ['bag', 'box', 'panel-pet', 'panel-radar']);
});
test('rotation bounds include rotated edges', () => {
    const bounds = rotatedBounds({ x: 50, y: 50, width: 100, height: 40, rotation: 90 });
    assert.ok(Math.abs(bounds.width - 40) < 0.001);
    assert.equal(bounds.height, 100);
    assert.equal(bounds.y, 20);
});
test('project validation rejects corrupted controls before rendering', () => {
    const base = { item_ids: [], show_details: true, positions: {}, groups: [], texts: [] };
    assert.equal(validateProject(base), base);
    for (const extra of [
        { layers: { bag: { scale: 'bad' } } },
        { document: { cname: {}, background: '#ffffff' } },
        { order: ['bag', 'bag'] },
        { shapes: [{ id: 'shape-1', stroke_width: -10 }] },
        { positions: { bag: null } },
    ]) {
        assert.throws(() => validateProject({ ...base, ...extra }));
    }
});

test('six handles resize separate axes and Shift preserves proportions with a fixed opposite corner', () => {
    assert.equal(RESIZE_HANDLES.length, 6);
    const block = { x: 200, y: 200, width: 200, height: 100, originalWidth: 200, originalHeight: 100, rotation: 0 };
    const se = RESIZE_HANDLES.find((handle) => handle.id === 'se');
    const free = resizeLayer(block, se, { x: 100, y: 0 });
    assert.deepEqual(free, { x: 200, y: 200, scale_x: 1.5, scale_y: 1 });
    const uniform = resizeLayer(block, se, { x: 100, y: 0 }, true);
    assert.equal(uniform.scale_x, uniform.scale_y);
    const nw = resizeLayer(block, RESIZE_HANDLES[0], { x: -100, y: -50 });
    assert.deepEqual(nw, { x: 100, y: 150, scale_x: 1.5, scale_y: 1.5 });
    const rotated = resizeLayer({ ...block, rotation: 90 }, se, { x: 0, y: 100 });
    assert.equal(rotated.scale_x, 1.5);
    assert.equal(rotated.scale_y, 1);
    const side = resizeLayer(
        block,
        RESIZE_HANDLES.find((handle) => handle.id === 'e'),
        { x: 9999, y: 100 },
    );
    assert.equal(side.scale_x, 3);
    assert.equal(side.scale_y, 1);
});

test('axis scales fall back to legacy scale and canvas dimensions survive project validation', () => {
    const [block] = applyLayers([{ key: 'bag', width: 100, height: 50 }], { bag: { scale: 2, scale_x: 0.5 } });
    assert.equal(block.width, 50);
    assert.equal(block.height, 100);
    const project = { item_ids: [], show_details: true, positions: {}, document: { cname: 'hero', background: '#ffffff', width: 500, height: 200 } };
    assert.equal(validateProject(project), project);
    for (const document of [
        { ...project.document, height: undefined },
        { ...project.document, width: 6000, height: 6000 },
    ]) {
        assert.throws(() => validateProject({ ...project, document }));
    }
});
