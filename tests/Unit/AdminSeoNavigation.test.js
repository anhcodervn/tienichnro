import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/layouts/admin/sidebar/navigation.ts', import.meta.url), 'utf8');
const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
const navigation = {};
new Function('require', 'exports', code)(createRequire(import.meta.url), navigation);

test('SEO has one parent menu and retains all destinations and page titles', () => {
    const groups = navigation.adminMenuGroups;
    const seo = groups.filter((group) => group.key === 'seo');
    assert.equal(seo.length, 1);
    assert.equal(seo[0].label, 'SEO');
    assert.equal(groups.filter((group) => group.href?.startsWith('/admin/seo/')).length, 0);
    assert.deepEqual(seo[0].children.map((child) => child.href), [
        '/admin/seo/posts', '/admin/seo/categories', '/admin/seo/home', '/admin/seo/sitemaps',
    ]);
    for (const child of seo[0].children) {
        assert.equal(navigation.resolveAdminPageTitle(child.href), child.label);
        assert.equal(navigation.resolveAdminPageTitle(`${child.href}/1/edit`), child.label);
    }
});
