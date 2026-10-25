import assert from 'node:assert/strict';
import { File } from 'node:buffer';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';

function uploadService() {
    const calls = [];
    const catalog = { original: { version: '1.0.0' }, update: null };
    const source = readFileSync(new URL('../../resources/js/services/admin-client-game.service.ts', import.meta.url), 'utf8');
    const compiled = ts.transpileModule(source, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const exports = {};
    const api = {
        get: async (url) => { calls.push(url); return { data: { data: catalog } }; },
        post: async (...args) => {
            calls.push(args);
            args[2].onUploadProgress({ loaded: 5, total: 10 });
            args[2].onUploadProgress({ loaded: 10, total: 10 });
            return { data: { data: catalog } };
        },
    };
    new Function('require', 'exports', compiled)(() => ({ default: api }), exports);
    return { service: exports.adminClientGame, calls, catalog };
}

test('client game catalog unwraps the API response', async () => {
    const { service, calls, catalog } = uploadService();
    assert.deepEqual(await service.catalog(), catalog);
    assert.equal(calls[0], '/api/admin-api/license/client-game');
});

for (const kind of ['original', 'update']) {
    test(`client game uploads ${kind} multipart with its own version and progress`, async () => {
        const { service, calls, catalog } = uploadService();
        const progress = [];
        const file = new File(['archive'], `${kind}.zip`, { type: 'application/zip' });
        const result = await service.upload(kind, '1.2.0', file, (value) => progress.push(value));
        const [url, body] = calls[0];
        assert.equal(url, `/api/admin-api/license/client-game/${kind}`);
        assert.ok(body instanceof FormData);
        assert.equal(body.get('version'), '1.2.0');
        assert.equal(body.get('zip').name, `${kind}.zip`);
        assert.equal(await body.get('zip').text(), 'archive');
        assert.deepEqual(progress, [50, 100]);
        assert.deepEqual(result, catalog);
    });
}
