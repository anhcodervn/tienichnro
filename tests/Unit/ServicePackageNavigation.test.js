import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';

const require = createRequire(import.meta.url);
function loadTypeScript(path) {
    const source = readFileSync(new URL(path, import.meta.url), 'utf8');
    const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;
    const exports = {};
    new Function('require', 'exports', code)(require, exports);
    return exports;
}

test('wallet and user management share the users submenu with registered routes and page titles', () => {
    const navigation = loadTypeScript('../../resources/js/layouts/admin/sidebar/navigation.ts');
    const router = loadTypeScript('../../resources/js/router/modules/admin/index.ts').default;
    const group = navigation.adminMenuGroups.find((item) => item.key === 'users');
    assert.deepEqual(
        group.children.map((child) => child.href),
        ['/admin/users', '/admin/wallets'],
    );
    assert.equal(group.href, undefined);
    assert.equal(
        navigation.adminMenuGroups.some((item) => item.key === 'wallets'),
        false,
    );
    for (const child of group.children) {
        assert.ok(router.children.some((route) => `/admin/${route.path}` === child.href));
        assert.equal(navigation.resolveAdminPageTitle(child.href), child.label);
        assert.equal(navigation.resolveAdminPageTitle(`${child.href}/123`), child.label);
    }
});

test('service menus are hidden and license management resolves to its registered route', () => {
    const navigation = loadTypeScript('../../resources/js/layouts/admin/sidebar/navigation.ts');
    const router = loadTypeScript('../../resources/js/router/modules/admin/index.ts').default;
    assert.equal(
        navigation.adminMenuGroups.some((item) => item.key === 'services'),
        false,
    );
    const group = navigation.adminMenuGroups.find((item) => item.key === 'licenses');
    assert.equal(group.href, undefined);
    assert.deepEqual(
        group.children.map((child) => child.href),
        ['/admin/licenses', '/admin/licenses/products', '/admin/licenses/plans'],
    );
    for (const child of group.children) {
        const route = router.children.find((route) => `/admin/${route.path}` === child.href);
        assert.ok(route);
        assert.equal(navigation.resolveAdminPageTitle(child.href), child.label);
    }
    assert.equal(router.children.find((route) => route.name === 'admin.licenses.products').props.section, 'products');
    assert.equal(router.children.find((route) => route.name === 'admin.licenses.plans').props.section, 'plans');
    assert.ok(router.children.some((route) => route.path === 'services'));
    assert.ok(router.children.some((route) => route.path === 'service-packages'));
});
