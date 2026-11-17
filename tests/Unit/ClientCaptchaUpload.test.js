import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeCaptchaUpload } from '../../resources/js/client-captcha.js';

function fixture() {
    const handlers = {};
    const input = {
        files: [],
        value: '',
        addEventListener(name, callback) {
            handlers[name] = callback;
        },
        dispatchEvent(event) {
            handlers[event.type](event);
        },
    };
    const classes = new Set();
    const dropHandlers = {};
    const dropzone = {
        classList: { toggle: (name, active) => (active ? classes.add(name) : classes.delete(name)) },
        addEventListener(name, callback) {
            dropHandlers[name] = callback;
        },
    };
    const preview = {
        hidden: true,
        removeAttribute() {
            delete this.src;
        },
    };
    const name = { textContent: '' };
    const errors = [];
    initializeCaptchaUpload(
        {
            elements: { image: input },
            querySelector: (selector) =>
                ({
                    '[data-captcha-dropzone]': dropzone,
                    '[data-captcha-preview]': preview,
                    '[data-captcha-file-name]': name,
                })[selector],
        },
        (message) => errors.push(message),
    );
    return {
        input,
        preview,
        name,
        errors,
        classes,
        change: () => handlers.change(),
        drag: (type, files = []) => dropHandlers[type]({ preventDefault() {}, dataTransfer: { files } }),
    };
}

test('file picker and drag drop preview images and release replaced previews', () => {
    const create = URL.createObjectURL;
    const revoke = URL.revokeObjectURL;
    const revoked = [];
    URL.createObjectURL = (file) => 'blob:' + file.name;
    URL.revokeObjectURL = (url) => revoked.push(url);
    try {
        const view = fixture();
        view.input.files = [{ name: 'one.png', size: 1024, type: 'image/png' }];
        view.change();
        assert.equal(view.preview.src, 'blob:one.png');
        assert.equal(view.preview.hidden, false);
        assert.equal(view.name.textContent, 'one.png');
        view.drag('dragenter');
        view.drag('dragenter');
        view.drag('dragleave');
        assert.equal(view.classes.has('!border-emerald-500'), true);
        view.drag('drop', [{ name: 'two.webp', size: 2048, type: 'image/webp' }]);
        assert.equal(view.preview.src, 'blob:two.webp');
        assert.deepEqual(revoked, ['blob:one.png']);
        assert.equal(view.classes.has('!border-emerald-500'), false);
        view.input.files = [];
        view.change();
        assert.equal(view.preview.hidden, true);
        assert.deepEqual(revoked, ['blob:one.png', 'blob:two.webp']);
    } finally {
        URL.createObjectURL = create;
        URL.revokeObjectURL = revoke;
    }
});

test('upload rejects multiple files unsupported formats and oversized images', () => {
    const view = fixture();
    view.drag('drop', [{}, {}]);
    assert.match(view.errors.at(-1), /một ảnh/);
    for (const file of [
        { type: 'image/svg+xml', size: 10 },
        { type: 'image/png', size: 2 * 1024 * 1024 + 1 },
    ]) {
        view.input.value = 'chosen';
        view.input.files = [file];
        view.change();
        assert.equal(view.input.value, '');
        assert.equal(view.preview.hidden, true);
        assert.match(view.errors.at(-1), /PNG, JPEG hoặc WebP/);
    }
});
