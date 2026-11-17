import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';
import * as vue from 'vue';
import * as autosave from '../../resources/js/game-image-autosave.js';
import * as capture from '../../resources/js/game-image-capture.js';
import * as model from '../../resources/js/game-image-editor-model.js';
import * as scene from '../../resources/js/game-image-scene.js';
import * as shortcuts from '../../resources/js/game-image-shortcuts.js';

const require = createRequire(import.meta.url);
async function studio(readOnly = false, logoUrl = '', accountExtras = {}) {
    const source = readFileSync(new URL('../../resources/js/components/game-image/GameImageEditor.vue', import.meta.url), 'utf8').replace(
        '</script>',
        'defineExpose({ keydown, releasePan, startPointer, tool, spacePan, selectedIds, texts, positions, zoom, canvas, stageSize, loadAssets, layerList, layerDrag, startLayerDrag, moveLayerDrag, endLayerDrag, cancelLayerDrag, blocks, order, layout, undo, redo, startResize, movePointer, stopPointer, documentSettings, dimensions, fitBackground, setBackgroundSize, layerSettings, save, dirty, savedSnapshot });\n</script>',
    );
    const { descriptor } = parse(source);
    const script = compileScript(descriptor, { id: 'studio-shortcut-test', inlineTemplate: true });
    const code = ts.transpileModule(script.content, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const exports = {};
    new Function('require', 'exports', code)((name) => {
        if (name.endsWith('game-image-capture.js')) return { ...capture, loadCaptureImage: async () => ({ naturalWidth: 100, naturalHeight: 100 }) };
        if (name.endsWith('game-image-autosave.js')) return autosave;
        if (name.endsWith('game-image-editor-model.js')) return model;
        if (name.endsWith('game-image-scene.js')) return scene;
        if (name.endsWith('game-image-shortcuts.js')) return shortcuts;
        return require(name);
    }, exports);
    let state;
    const setup = exports.default.setup;
    exports.default.setup = async (props, context) => {
        const render = setup(props, {
            ...context,
            expose: (value) => {
                state = value;
            },
        });
        await state.loadAssets();
        return render;
    };
    const html = await renderToString(
        vue.createSSRApp(exports.default, {
            account: { id: 1, username: 'demo', server: '1', layout: null, ...accountExtras },
            demoBase: '/images/demo/capture/kztiger',
            indexUrl: '/',
            readOnly,
            logoUrl,
        }),
    );
    return { ...state, html };
}

test('captured ZIP images expand an undersized saved background once without restricting later manual resizing', async () => {
    const state = await studio(false, '', {
        capture_urls: Object.fromEntries(capture.PANEL_FILES.map((file) => [file, '/uploads/capture/' + file])),
        layout: { item_ids: [], show_details: true, positions: {}, groups: [], document: { cname: 'demo', background: '#ffffff', width: 64, height: 64 } },
    });
    const required = scene.sceneSize(state.blocks.value);
    assert.ok(state.blocks.value.length > 0);
    assert.ok(state.dimensions.value.width >= required.width);
    assert.ok(state.dimensions.value.height >= required.height);
    assert.equal(state.dirty.value, true);
    state.setBackgroundSize(100, 100);
    await state.loadAssets();
    assert.deepEqual(state.dimensions.value, { width: 100, height: 100 });
});

test('studio renders the website logo and a readable fallback when no logo is configured', async () => {
    const branded = await studio(false, '/uploads/branding/logo.webp');
    assert.match(branded.html, /src="\/uploads\/branding\/logo.webp" alt="Tiện ích NRO"/);
    assert.ok(!branded.html.includes('Image Studio'));
    const fallback = await studio();
    assert.ok(fallback.html.includes('studio-brand-fallback'));
    assert.ok(!fallback.html.includes('studio-brand-image'));
});

test('studio connects tools and shortcuts without changing selection when panning or typing', async () => {
    const state = await studio();
    const press = (key, extra = {}) => state.keydown({ key, preventDefault() {}, ...extra });
    state.selectedIds.value = ['bag'];
    press('d', { ctrlKey: true });
    assert.deepEqual(state.selectedIds.value, []);
    press(' ');
    assert.equal(state.spacePan.value, true);
    assert.equal(state.tool.value, 'select');
    state.releasePan({ type: 'blur' });
    assert.equal(state.spacePan.value, false);
    press('t', { target: { closest: () => ({}) } });
    assert.equal(state.tool.value, 'select');
    press('t');
    assert.equal(state.tool.value, 'text');
    assert.equal(state.texts.value.length, 0);
    state.canvas.value = {
        getBoundingClientRect: () => ({ left: 0, top: 0, width: state.stageSize.value.width, height: state.stageSize.value.height }),
    };
    state.startPointer({ button: 0, clientX: 160, clientY: 100, preventDefault() {} });
    assert.equal(state.texts.value.length, 1);
    assert.deepEqual(state.positions.value['text-1'], { x: 160, y: 100 });
    press('j', { ctrlKey: true });
    assert.equal(state.texts.value.length, 2);
    press('t', { ctrlKey: true });
    assert.equal(state.tool.value, 'resize');
    press('Enter');
    assert.equal(state.tool.value, 'select');
    press('1', { ctrlKey: true });
    assert.equal(state.zoom.value, 1);
});

test('layer pointer dragging updates saved stacking order and supports undo redo and cancellation', async () => {
    const state = await studio();
    const original = state.blocks.value.map((block) => block.key);
    let hit = { dataset: { layerKey: original.at(-1) }, getBoundingClientRect: () => ({ top: 100, height: 56 }) };
    state.layerList.value = {
        ownerDocument: { elementFromPoint: () => (hit ? { closest: () => hit } : null) },
        contains: () => true,
        scrollTop: 0,
        getBoundingClientRect: () => ({ top: 0, bottom: 500 }),
    };
    let captured = false;
    const handle = {
        setPointerCapture: () => {
            captured = true;
        },
        hasPointerCapture: () => captured,
        releasePointerCapture: () => {
            captured = false;
        },
    };
    const event = (clientY) => ({ pointerId: 7, pointerType: 'touch', button: 0, clientX: 20, clientY, currentTarget: handle, preventDefault() {} });
    state.startLayerDrag(event(400), original[0]);
    state.moveLayerDrag(event(110));
    assert.equal(state.layerDrag.value.placement, 'before');
    state.endLayerDrag(event(110));
    assert.equal(captured, false);
    assert.equal(state.layerDrag.value, null);
    assert.equal(state.order.value.at(-1), original[0]);
    assert.deepEqual(state.layout.value.order, state.order.value);
    state.undo();
    assert.deepEqual(
        state.blocks.value.map((block) => block.key),
        original,
    );
    state.redo();
    const reordered = [...state.order.value];
    assert.equal(reordered.at(-1), original[0]);
    state.startLayerDrag(event(400), original[1]);
    state.moveLayerDrag(event(110));
    state.cancelLayerDrag(event(110));
    assert.deepEqual(state.order.value, reordered);
    state.startLayerDrag(event(400), original[1]);
    hit = null;
    state.moveLayerDrag(event(110));
    state.endLayerDrag(event(110));
    assert.deepEqual(state.order.value, reordered);
});

test('shared viewers cannot start a layer drag', async () => {
    const state = await studio(true);
    state.startLayerDrag({ button: 0, pointerId: 1 }, 'bag');
    assert.equal(state.layerDrag.value, null);
    assert.deepEqual(state.order.value, []);
});

test('studio resize handles update persisted geometry support Shift and undo and resize the background', async () => {
    const state = await studio();
    state.selectedIds.value = ['bag'];
    state.canvas.value = {
        getBoundingClientRect: () => ({ left: 0, top: 0, width: state.stageSize.value.width, height: state.stageSize.value.height }),
        hasPointerCapture: () => false,
    };
    const event = (x, y, extra = {}) => ({
        button: 0,
        pointerId: 9,
        clientX: x,
        clientY: y,
        currentTarget: { setPointerCapture() {} },
        preventDefault() {},
        ...extra,
    });
    const initial = { ...state.blocks.value.find((block) => block.key === 'bag') };
    state.startResize(event(0, 0), 'se');
    state.movePointer(event(50, 0));
    assert.equal(state.layout.value.layers.bag.scale_x, 1.5);
    assert.equal(state.layout.value.layers.bag.scale_y, 1);
    state.stopPointer(event(50, 0));
    state.undo();
    assert.equal(state.blocks.value.find((block) => block.key === 'bag').width, initial.width);
    state.selectedIds.value = ['bag'];
    state.startResize(event(0, 0), 'se');
    state.movePointer(event(50, 0, { shiftKey: true }));
    assert.equal(state.layout.value.layers.bag.scale_x, state.layout.value.layers.bag.scale_y);
    state.stopPointer(event(50, 0));
    state.setBackgroundSize(800, 400);
    state.startResize(event(0, 0), 'e', true);
    state.movePointer(event(100, 100));
    state.stopPointer(event(100, 100));
    assert.deepEqual(state.dimensions.value, { width: 900, height: 400 });
    state.fitBackground();
    assert.ok(state.dimensions.value.width >= 600);
    const viewer = await studio(true);
    viewer.startResize(event(0, 0), 'se', true);
    viewer.setBackgroundSize(100, 100);
    assert.equal(viewer.documentSettings.value.width, undefined);
});

test('saving exports the chosen canvas size and a response cannot mark newer edits as saved', async () => {
    const state = await studio(false, '', { update_url: '/private-save' });
    state.setBackgroundSize(800, 400);
    const originalDocument = globalThis.document;
    const originalFetch = globalThis.fetch;
    let finish;
    let body;
    let previewSize;
    const context = { clearRect() {}, fillRect() {}, fillText() {}, drawImage() {} };
    globalThis.document = {
        querySelector: () => ({ content: 'csrf-token' }),
        createElement: () => {
            const output = {
                getContext: () => context,
                toDataURL: () => {
                    previewSize = [output.width, output.height];
                    return 'data:image/png;base64,preview';
                },
            };
            return output;
        },
    };
    globalThis.fetch = async (_url, options) => {
        body = JSON.parse(options.body);
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-token');
        return new Promise((resolve) => {
            finish = () => resolve({ ok: true, status: 200, json: async () => ({}) });
        });
    };
    try {
        const pending = state.save(true);
        assert.deepEqual(previewSize, [800, 400]);
        assert.equal(body.layout.document.width, 800);
        state.setBackgroundSize(900, 450);
        finish();
        assert.equal(await pending, true);
        assert.equal(state.dirty.value, true);
        assert.equal(JSON.parse(state.savedSnapshot.value).document.width, 800);
    } finally {
        globalThis.document = originalDocument;
        globalThis.fetch = originalFetch;
    }
});
