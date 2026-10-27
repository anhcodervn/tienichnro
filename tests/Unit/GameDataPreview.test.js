import assert from 'node:assert/strict';
import test from 'node:test';
import { characterLayout, initializeGameDataPage, renderGameDataPreview } from '../../resources/js/client-game-data.js';

test('NPC parts retain native sizes and align head body and legs around their shared center', () => {
    const images = [
        { naturalWidth: 16, naturalHeight: 20 },
        { naturalWidth: 30, naturalHeight: 15 },
        { naturalWidth: 20, naturalHeight: 12 },
    ];
    const layout = characterLayout(images);
    assert.equal(layout.width, 30);
    assert.equal(layout.height, 47);
    assert.deepEqual(
        layout.pieces.map(({ x, y }) => [x, y]),
        [
            [7, 0],
            [0, 20],
            [5, 35],
        ],
    );
    assert.equal(layout.pieces[0].image, images[0]);
});

function previewContainer(decode = () => Promise.resolve()) {
    const draws = [];
    const doc = {
        createElement(tag) {
            return {
                tagName: tag.toUpperCase(),
                ownerDocument: doc,
                dataset: {},
                children: [],
                naturalWidth: 16,
                naturalHeight: 20,
                complete: false,
                appendChild(child) {
                    this.children.push(child);
                },
                prepend(child) {
                    this.children.unshift(child);
                },
                replaceChildren() {
                    this.children = [];
                },
                setAttribute() {},
                addEventListener() {},
                decode,
                querySelectorAll() {
                    return this.children.filter((child) => child.tagName === 'IMG');
                },
                getContext() {
                    return { drawImage: (...args) => draws.push(args) };
                },
                toDataURL() {
                    return 'data:image/png;base64,composed';
                },
            };
        },
    };
    return { container: doc.createElement('div'), draws };
}

const completePreview = {
    parts: {
        head: { id: 1, image: '/storage/head.png' },
        body: { id: 2, image: '/storage/body.png' },
        leg: { id: 3, image: '/storage/leg.png' },
    },
};

test('NPC preview draws all three parts and exposes the generated image for the lightbox', async () => {
    const { container, draws } = previewContainer();
    await renderGameDataPreview(container, completePreview, 'NPC');
    assert.equal(draws.length, 3);
    assert.deepEqual(
        draws.map(([, x, y]) => [x, y]),
        [
            [0, 0],
            [0, 20],
            [0, 40],
        ],
    );
    assert.equal(container.children[0].src, 'data:image/png;base64,composed');
    assert.equal(container.children[0].tabIndex, 0);
    assert.equal(container.children.length, 4);
});

test('incomplete NPC preview shows available parts without fabricating a complete character', async () => {
    const { container, draws } = previewContainer();
    await renderGameDataPreview(container, { parts: { ...completePreview.parts, leg: { id: 3, image: null } } });
    assert.equal(draws.length, 0);
    assert.equal(container.children.length, 3);
    assert.equal(container.children.at(-1).tagName, 'P');
});

test('switching details during image loading cannot insert the previous NPC into the next preview', async () => {
    let finish;
    const pending = new Promise((resolve) => {
        finish = resolve;
    });
    const { container, draws } = previewContainer(() => pending);
    const first = renderGameDataPreview(container, completePreview, 'NPC');
    await renderGameDataPreview(container, { image: '/storage/map.png' }, 'Map');
    finish();
    await first;
    assert.equal(draws.length, 0);
    assert.equal(container.children.length, 1);
    assert.equal(container.children[0].children[0].src, '/storage/map.png');
});

test('preview images reject executable and protocol relative URLs', async () => {
    const { container } = previewContainer();
    await renderGameDataPreview(container, { image: 'javascript:alert(1)' });
    assert.equal(container.children.length, 0);
    await renderGameDataPreview(container, { image: '//evil.example/map.png' });
    assert.equal(container.children.length, 0);
});

test('lightbox opens above details supports zoom and restores scroll on close', () => {
    const listeners = {};
    const { container } = previewContainer();
    const doc = container.ownerDocument;
    const create = doc.createElement;
    doc.createElement = (tag) => {
        const element = create(tag);
        element.style = {};
        element.listeners = {};
        element.append = (...children) => element.children.push(...children);
        element.addEventListener = (event, handler) => {
            element.listeners[event] = handler;
        };
        element.showModal = () => {
            element.open = true;
        };
        element.close = () => {
            element.open = false;
            element.listeners.close();
        };
        return element;
    };
    doc.querySelectorAll = () => [];
    doc.querySelector = (selector) => (selector.includes(',') ? container : null);
    doc.addEventListener = (name, handler) => {
        listeners[name] = handler;
    };
    doc.documentElement = { dataset: {}, style: { overflow: 'hidden' } };
    doc.body = doc.createElement('body');
    const previousElement = globalThis.Element;
    globalThis.Element = class {};
    try {
        initializeGameDataPage(doc);
        const target = new globalThis.Element();
        target.closest = () => ({ tagName: 'IMG', src: '/map.png', alt: 'Map' });
        listeners.click({ target });
        const viewer = doc.body.children[0];
        const [controls, image] = viewer.children;
        assert.equal(viewer.open, true);
        assert.equal(image.src, '/map.png');
        controls.children[2].listeners.click();
        assert.equal(image.style.width, '24px');
        controls.children[0].listeners.click();
        assert.equal(viewer.open, false);
        assert.equal(doc.documentElement.style.overflow, 'hidden');
    } finally {
        globalThis.Element = previousElement;
    }
});
