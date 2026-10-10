import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';
import { createSSRApp, h } from 'vue';

const source = readFileSync(new URL('../../resources/js/pages/admin/licenses/presentation.ts', import.meta.url), 'utf8');
const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
const presentation = {};
new Function('exports', code)(presentation);

const require = createRequire(import.meta.url);
const pageSource = readFileSync(new URL('../../resources/js/pages/admin/licenses/index.vue', import.meta.url), 'utf8');
const { descriptor } = parse(pageSource);
const compiledPage = compileScript(descriptor, { id: 'license-admin-test', inlineTemplate: true });
const pageCode = ts.transpileModule(compiledPage.content, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText;
const pageModule = {};
new Function('require', 'exports', pageCode)((name) => {
    if (name === '@/services/admin-license.service') return { adminLicense: {} };
    if (name === '@/utils/response') return { handleErrorResponse() {} };
    if (name === './presentation') return presentation;
    if (name === '@/components/shared/DataTable/index.vue') {
        return {
            default: {
                props: ['columns'],
                setup: (props) => () =>
                    h(
                        'table',
                        props.columns.map((column) => h('th', column.header)),
                    ),
            },
        };
    }
    return require(name);
}, pageModule);

for (const [section, title, column] of [
    ['keys', 'License keys', 'Trạng thái key'],
    ['products', 'Sản phẩm / tool', 'Heartbeat'],
    ['plans', 'Gói license', 'Giá'],
]) {
    test(`${section} submenu renders its own title and DataTable without catalog tabs or item cards`, async () => {
        const html = await renderToString(createSSRApp(pageModule.default, { section }));
        assert.ok(html.includes(title));
        assert.ok(html.includes(`<th>${column}</th>`));
        assert.equal(html.includes('<article'), false);
        assert.equal(html.includes('<nav'), false);
    });
}

test('catalog search and status filters combine without mutating the source rows', () => {
    const rows = [
        { id: 1, name: 'Tool nội bộ', product_code: 'NRO_MANAGER', is_active: true },
        { id: 2, name: 'Tool cũ', product_code: 'NRO_OLD', is_active: false },
    ];
    assert.deepEqual(
        presentation.licenseCatalogRows(rows, { search: ' nro_', status: 'active', product_id: '' }).map((row) => row.id),
        [1],
    );
    assert.deepEqual(
        presentation.licenseCatalogRows(rows, { search: '', status: 'inactive', product_id: '' }).map((row) => row.id),
        [2],
    );
    assert.equal(rows.length, 2);
    assert.equal(presentation.licenseCatalogRows(rows, { search: 'missing', status: '', product_id: '' }).length, 0);
});

test('plan catalog combines name, product and enabled status filters', () => {
    const rows = [
        { id: 1, name: 'Gói tháng', product_id: 1, is_active: true },
        { id: 2, name: 'Gói tháng', product_id: 2, is_active: true },
        { id: 3, name: 'Gói tháng cũ', product_id: 1, is_active: false },
    ];
    assert.deepEqual(
        presentation.licenseCatalogRows(rows, { search: 'GÓI THÁNG', status: 'active', product_id: '1' }).map((row) => row.id),
        [1],
    );
});

test('issuing only offers enabled plans belonging to enabled products', () => {
    const products = [
        { id: 1, is_active: true },
        { id: 2, is_active: false },
    ];
    const plans = [
        { id: 1, product_id: 1, is_active: true },
        { id: 2, product_id: 1, is_active: false },
        { id: 3, product_id: 2, is_active: true },
        { id: 4, product_id: 99, is_active: true },
    ];
    assert.deepEqual(
        presentation.availableLicensePlans(products, plans).map((plan) => plan.id),
        [1],
    );
});

test('revoked keys cannot be managed and device actions require a registered device', () => {
    assert.deepEqual(presentation.licenseActions({ status: 'revoked' }), []);
    assert.deepEqual(presentation.licenseActions({ status: 'unused', current_device_uuid: null, devices: [] }), ['extend', 'suspend', 'revoke']);
    const suspended = presentation.licenseActions({
        status: 'suspended',
        current_device_uuid: 'a',
        devices: [{ device_uuid: 'a' }, { device_uuid: 'b' }],
    });
    assert.ok(suspended.includes('resume'));
    assert.ok(suspended.includes('transfer'));
    assert.equal(suspended.includes('suspend'), false);
});

test('a suspended unactivated key is not presented as a perpetual key', () => {
    assert.equal(presentation.licenseExpiryLabel({ activated_at: null, expires_at: null }), 'Chưa kích hoạt');
    assert.equal(presentation.licenseExpiryLabel({ activated_at: '2026-10-10T00:00:00Z', expires_at: null }), 'Vĩnh viễn');
});

test('elapsed leases appear expired before scheduler updates their database status', () => {
    const expiry = '2026-10-10T00:01:00Z';
    assert.equal(presentation.effectiveSessionStatus({ status: 'active', lease_expires_at: expiry }, Date.parse(expiry)), 'expired');
    assert.equal(presentation.effectiveSessionStatus({ status: 'revoked', lease_expires_at: expiry }, Date.parse(expiry)), 'revoked');
});

test('saving and unsaved one-time keys prevent accidental dialog dismissal', () => {
    assert.equal(presentation.canCloseLicenseDialog(true, 'product', true), false);
    assert.equal(presentation.canCloseLicenseDialog(false, 'issued', false), false);
    assert.equal(presentation.canCloseLicenseDialog(false, 'issued', true), true);
    assert.equal(presentation.canCloseLicenseDialog(false, 'product', false), true);
});
