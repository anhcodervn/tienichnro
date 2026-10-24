import assert from 'node:assert/strict';
import { File } from 'node:buffer';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';

function uploadService() {
    const calls = [];
    const source = readFileSync(new URL('../../resources/js/services/admin-license.service.ts', import.meta.url), 'utf8');
    const compiled = ts.transpileModule(source, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const exports = {};
    new Function('require', 'exports', compiled)(() => ({ default: { post: async (...args) => { calls.push(args); } } }), exports);
    return { service: exports.adminLicense, calls };
}

test('product upload sends multipart POST with PATCH override and both ZIP files', async () => {
    const { service, calls } = uploadService();
    const tool = new File(['tool'], 'tool.zip', { type: 'application/zip' });
    const update = new File(['update'], 'update.zip', { type: 'application/zip' });
    await service.saveProduct({ name: 'My tool', image_url: '/storage/uploads/mod.webp', latest_version: '2.0.0', is_active: false, tool_zip_path: 'untrusted-path' }, 17, { tool, update });
    const [url, body] = calls[0];
    assert.equal(url, '/api/admin-api/license/products/17');
    assert.ok(body instanceof FormData);
    assert.equal(body.get('_method'), 'PATCH');
    assert.equal(body.get('is_active'), '0');
    assert.equal(body.get('latest_version'), '2.0.0');
    assert.equal(body.get('image_url'), '/storage/uploads/mod.webp');
    assert.equal(body.get('tool_zip').name, 'tool.zip');
    assert.equal(body.get('update_zip').name, 'update.zip');
    assert.equal(body.has('tool_zip_path'), false);
});

test('creating or editing without selecting ZIPs sends no replacement files', async () => {
    const { service, calls } = uploadService();
    await service.saveProduct({ name: 'My tool', description: null, is_active: true }, null);
    const [url, body] = calls[0];
    assert.equal(url, '/api/admin-api/license/products');
    assert.equal(body.get('is_active'), '1');
    assert.equal(body.get('description'), '');
    assert.equal(body.has('_method'), false);
    assert.equal(body.has('tool_zip'), false);
    assert.equal(body.has('update_zip'), false);
});
