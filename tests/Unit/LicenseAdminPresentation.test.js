import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { File } from 'node:buffer';
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
function compilePage(source = pageSource, row = { id: 1, status: 'unused' }, options = {}) {
    const { descriptor } = parse(source);
    const compiledPage = compileScript(descriptor, { id: 'license-admin-test', inlineTemplate: options.inlineTemplate ?? true });
    const pageCode = ts.transpileModule(compiledPage.content, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const pageModule = {};
    new Function('require', 'exports', pageCode)((name) => {
        if (name === '@/services/admin-license.service') return { adminLicense: options.service ?? {} };
        if (name === 'vue' && options.inlineTemplate === false) return { ...require(name), onMounted() {} };
        if (name === '@/utils/response') return { handleErrorResponse() {} };
        if (name === '@/components/shared/UpladImage/index.vue') {
            return {
                default: {
                    props: ['squareSize', 'imageSrc', 'disabled'],
                    setup: (props) => () => h('div', { 'data-square-size': props.squareSize, 'data-image-src': props.imageSrc }),
                },
            };
        }
        if (name === './presentation') return presentation;
        if (name === '@headlessui/vue') {
            const panel = {
                setup:
                    (_, { slots }) =>
                    () =>
                        h('div', slots.default?.()),
            };
            return {
                Dialog: {
                    props: ['open'],
                    setup:
                        (props, { slots }) =>
                        () =>
                            props.open ? h('div', slots.default?.()) : null,
                },
                DialogPanel: panel,
                DialogTitle: panel,
                DialogDescription: panel,
            };
        }
        if (name === '@/components/shared/DataTable/index.vue') {
            return {
                default: {
                    props: ['columns'],
                    setup:
                        (props, { slots }) =>
                        () =>
                            h('table', [
                                ...props.columns.map((column) => h('th', column.header)),
                                slots.key_prefix?.({ row }),
                                slots.actions?.({ row }),
                            ]),
                },
            };
        }
        return require(name);
    }, pageModule);
    return pageModule.default;
}
const pageModule = { default: compilePage() };

for (const [status, data, expected] of [
    [422, { errors: { latest_version: ['Phiên bản không hợp lệ.'] } }, 'Phiên bản không hợp lệ.'],
    [422, { errors: {}, message: 'ZIP không hợp lệ.' }, 'ZIP không hợp lệ.'],
    [413, '<html>Request too large</html>', 'dung lượng tải lên vượt giới hạn'],
    [401, {}, 'đăng nhập lại'],
    [419, {}, 'tải lại trang'],
    [500, '<html>Server error</html>', 'HTTP 500'],
    [undefined, undefined, 'kiểm tra mạng'],
]) {
    test(`failed product update (${status ?? 'network'}) keeps the form and selected ZIP with a visible error`, async () => {
        const file = new File(['zip'], 'tool-1.0.3.zip', { type: 'application/zip' });
        const error = { isAxiosError: true, response: status ? { status, data } : undefined };
        const service = {
            saveProduct: async () => {
                throw error;
            },
        };
        const page = compilePage(pageSource, undefined, { inlineTemplate: false, service });
        const state = page.setup({ section: 'products' }, { expose() {} });
        state.editProduct({ id: 7, name: 'Tool', latest_version: '1.0.2' });
        state.product.latest_version = '1.0.3';
        state.productFiles.tool = file;
        await state.save();
        assert.equal(state.modal.value, 'product');
        assert.equal(state.productFiles.tool, file);
        assert.equal(state.product.latest_version, '1.0.3');
        assert.equal(state.saving.value, false);
        assert.ok(state.formErrors.value.some((message) => message.includes(expected)));
    });
}

test('successful product update closes the dialog and shows confirmation', async () => {
    const calls = [];
    const service = {
        saveProduct: async (...args) => {
            calls.push(args);
        },
        products: async () => [{ id: 7, latest_version: '1.0.3' }],
        plans: async () => [],
        keys: async () => ({ data: [], meta: { last_page: 1, total: 0 } }),
    };
    const page = compilePage(pageSource, undefined, { inlineTemplate: false, service });
    const state = page.setup({ section: 'products' }, { expose() {} });
    state.editProduct({ id: 7, latest_version: '1.0.2' });
    state.product.latest_version = '1.0.3';
    await state.save();
    assert.equal(calls.length, 1);
    assert.equal(calls[0][1], 7);
    assert.equal(state.modal.value, null);
    assert.equal(state.notice.value, 'Đã lưu thay đổi.');
    assert.equal(state.products.value[0].latest_version, '1.0.3');
});

test('native validation errors are reported beside the save button', async () => {
    const page = compilePage(pageSource, undefined, { inlineTemplate: false });
    const state = page.setup({ section: 'products' }, { expose() {} });
    state.showInvalidField({ target: { validationMessage: 'Vui lòng nhập phiên bản.' } });
    assert.deepEqual(state.formErrors.value, ['Vui lòng nhập phiên bản.']);
    const source = pageSource
        .replace(/const modal = ref<[^\n]+>\(null\);/, "const modal = ref('product');")
        .replace('const formErrors = ref<string[]>([]);', `const formErrors = ref(${JSON.stringify(state.formErrors.value)});`);
    const html = await renderToString(createSSRApp(compilePage(source), { section: 'products' }));
    assert.match(html, /<footer[^>]*>[\s\S]*role="alert"[\s\S]*Vui lòng nhập phiên bản\.[\s\S]*type="submit"[\s\S]*<\/footer>/);
});

test('product form reuses square image uploader and blocks save while an image uploads', async () => {
    const source = pageSource
        .replace(/const modal = ref<[^\n]+>\(null\);/, "const modal = ref('product');")
        .replace('ref<{ loading: boolean } | null>(null)', 'ref({ loading: true })');
    const html = await renderToString(createSSRApp(compilePage(source), { section: 'products' }));
    assert.ok(html.includes('Ảnh bản mod (1:1)'));
    assert.ok(html.includes('data-square-size="512"'));
    assert.match(html, /type="submit"[^>]*disabled/);
});

for (const section of ['products', 'plans']) {
    test(`${section} offers create edit and delete controls`, async () => {
        const html = await renderToString(createSSRApp(compilePage(), { section }));
        assert.ok(html.includes(section === 'products' ? 'Thêm sản phẩm' : 'Thêm gói license'));
        assert.ok(html.includes('Chỉnh sửa'));
        assert.ok(html.includes('>Xóa</button>'));
    });
}

test('catalog deletion names the selected item and requires confirmation', async () => {
    const source = pageSource
        .replace(/const modal = ref<[^\n]+>\(null\);/, "const modal = ref('catalog-delete');")
        .replace(/const catalogDeletion = ref<[^\n]+>\(null\);/, 'const catalogDeletion = ref({ id: 7, name: "Demo tool", kind: "products" });');
    const html = await renderToString(createSSRApp(compilePage(source), { section: 'products' }));
    assert.ok(html.includes('Demo tool'));
    assert.ok(html.includes('Tôi xác nhận xóa mục này.'));
    assert.match(html, /type="checkbox" required/);
    assert.match(html, /type="submit"[^>]*disabled/);
});

test('eye control is visible beside the license key in the table', async () => {
    const source = pageSource.replace('const loadingKeys = ref(true)', 'const loadingKeys = ref(false)');
    const html = await renderToString(createSSRApp(compilePage(source, { id: 7, key_prefix: 'ABCD-1234', status: 'unused' }), { section: 'keys' }));
    assert.ok(html.includes('ABCD-1234…'));
    assert.ok(html.includes('aria-label="Xem license key"'));
});

test('eye control stays visible in detail for a legacy key', async () => {
    const source = pageSource
        .replace(/const modal = ref<[^\n]+>\(null\);/, "const modal = ref('detail');")
        .replace('const detail = ref<LicenseKey | null>(null);', 'const detail = ref({ id: 7, key_prefix: "ABCD-1234", status: "unused" });');
    const html = await renderToString(createSSRApp(compilePage(source), { section: 'keys' }));
    assert.ok(html.includes('aria-label="Hiện key đầy đủ"'));
});

for (const visible of [false, true]) {
    test(`detail ${visible ? 'reveals' : 'masks'} the full key with an accessible eye control`, async () => {
        const fixture = { id: 7, key_prefix: 'ABCD-1234', key: 'ABCD-1234-SECRET-FULL-KEY', status: 'unused', activated_at: null, expires_at: null };
        const source = pageSource
            .replace(/const modal = ref<[^\n]+>\(null\);/, "const modal = ref('detail');")
            .replace('const detail = ref<LicenseKey | null>(null);', `const detail = ref(${JSON.stringify(fixture)});`)
            .replace('const keyVisible = ref(false);', `const keyVisible = ref(${visible});`);
        const html = await renderToString(createSSRApp(compilePage(source), { section: 'keys' }));
        assert.equal(html.includes(fixture.key), visible);
        assert.ok(html.includes(visible ? 'Ẩn key' : 'Hiện key đầy đủ'));
        assert.ok(html.includes(`aria-pressed="${visible}"`));
    });
}

test('key rows expose detail edit rotation and deletion controls', async () => {
    const page = compilePage(pageSource.replace('const loadingKeys = ref(true)', 'const loadingKeys = ref(false)'));
    const html = await renderToString(createSSRApp(page, { section: 'keys' }));
    for (const label of ['Chi tiết', 'Sửa', 'Đổi key', 'Xóa']) assert.ok(html.includes(label));
});

test('revoked key rows retain detail and deletion without edit or rotation', async () => {
    const page = compilePage(pageSource.replace('const loadingKeys = ref(true)', 'const loadingKeys = ref(false)'), { id: 1, status: 'revoked' });
    const html = await renderToString(createSSRApp(page, { section: 'keys' }));
    assert.ok(html.includes('Chi tiết'));
    assert.ok(html.includes('Xóa'));
    assert.equal(html.includes('Đổi key'), false);
    assert.equal(html.includes('>Sửa<'), false);
});

for (const [section, title, column] of [
    ['products', 'Sản phẩm / tool', 'Heartbeat'],
    ['plans', 'Gói license', 'Giá'],
    ['keys', 'License keys', 'Trạng thái key'],
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
    assert.deepEqual(presentation.licenseActions({ status: 'unused', current_device_uuid: null, devices: [] }), [
        'extend',
        'suspend',
        'rotate',
        'revoke',
    ]);
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
