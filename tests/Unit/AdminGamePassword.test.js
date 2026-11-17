import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';
import * as vue from 'vue';

const require = createRequire(import.meta.url);
async function screen(page, overrides = {}, confirmed = true, confirmationValue) {
    const calls = [];
    const service = {
        configuration: async () => ({
            price: null,
            has_api_key: true,
            captcha_provider: 'autocaptcha',
            providers: [
                { id: 'captcha69', label: 'Captcha69', has_api_key: true, has_proxy: true },
                { id: 'autocaptcha', label: 'AutoCaptcha', has_api_key: true, has_proxy: false },
            ],
        }),
        configure: async (data) => {
            calls.push(data);
            return {
                price: data.price,
                has_api_key: true,
                captcha_provider: data.captcha_provider,
                providers: [{ id: data.captcha_provider, label: data.captcha_provider, has_api_key: true, has_proxy: true }],
            };
        },
        history: async (params) => {
            calls.push(params);
            return {
                data: [
                    {
                        id: 1,
                        username: '<script>name</script>',
                        member: 'owner',
                        server_code: 3,
                        status: 'needs_review',
                        status_label: 'Cần kiểm tra',
                        progress: 'Verify',
                        error_code: 'VERIFY_FAILED',
                        error_message: '<script>bad</script>',
                        charged_amount: 100,
                        created_at: '2026-11-16T00:00:00Z',
                    },
                ],
                meta: { current_page: params.page, last_page: 2, total: 21 },
            };
        },
        resolve: async (id, status) => calls.push({ id, status }),
        retry: async (id, data) => calls.push({ retry: id, data }),
        clearHistory: async (days) => {
            calls.push({ clear: days });
            return { deleted: 10, has_more: true };
        },
        clearSelected: async (ids) => {
            calls.push({ deletedIds: ids });
            return { deleted: ids.length, skipped: 0 };
        },
        ...overrides,
    };
    const source = readFileSync(new URL(`../../resources/js/pages/admin/game-password/${page}.vue`, import.meta.url), 'utf8').replace(
        '</script>',
        `defineExpose({ load, ${page === 'configuration' ? 'save, price, saving, errorMessage, openSeo, seo, tab, seoLoading, seoError' : 'resolve, retry, retrying, q, status, page, rows, resolving, clearHistory, clearing, retentionDays, cleanupMessage, closeStream, visibilityChanged, selectedIds, toggleAll, deleteSelected, syncSelection, allSelected'} });</script>`,
    );
    const { descriptor } = parse(source);
    const code = ts.transpileModule(compileScript(descriptor, { id: `password-${page}`, inlineTemplate: true }).content, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const exports = {};
    new Function('require', 'exports', code)((name) => {
        if (name.includes('admin-service-management.service'))
            return {
                adminServiceManagement: {
                    get: async (code) => {
                        calls.push({ seoCode: code });
                        return { seo: { title: 'Password SEO', lock_version: 0 } };
                    },
                },
            };
        if (name.includes('ToolSeoEditor')) return { default: { props: ['code', 'modelValue'], setup: () => () => vue.h('div') } };
        if (name.includes('admin-game-password.service'))
            return { adminGamePasswordService: service, gamePasswordStatuses: { needs_review: 'Cần kiểm tra' } };
        if (name.includes('utils/response')) return { handleErrorResponse() {}, handleSuccessResponse() {} };
        if (name === 'sweetalert2') return { default: { fire: async () => ({ isConfirmed: confirmed, value: confirmationValue }) } };
        if (name.includes('DataTable'))
            return {
                default: {
                    props: ['data'],
                    setup:
                        (props, { slots }) =>
                        () =>
                            vue.h(
                                'table',
                                props.data.flatMap((row) => Object.values(slots).map((slot) => vue.h('tr', slot({ row })))),
                            ),
                },
            };
        if (name.startsWith('@/components/')) return { default: { setup: () => () => vue.h('div') } };
        return require(name);
    }, exports);
    let state;
    const setup = exports.default.setup;
    exports.default.setup = async (props, context) => {
        const render = setup(props, { ...context, expose: (value) => (state = value) });
        await state.load();
        return render;
    };
    const html = await renderToString(vue.createSSRApp(exports.default));
    return { state, html, calls };
}

test('admin configures only a price and accepts zero as free', async () => {
    const f = await screen('configuration');
    await f.state.save();
    assert.equal(f.calls.length, 0);
    f.state.price.value = 0;
    await f.state.save();
    assert.deepEqual(f.calls[0], { price: 0 });
    assert.equal(f.state.saving.value, false);
    assert.ok(!f.html.includes('Captcha69'));
    assert.ok(!f.html.includes('<select'));
    assert.equal((f.html.match(/<input/g) || []).length, 1);
});

test('password configuration opens the shared SEO editor for the correct tool and retains its draft', async () => {
    const f = await screen('configuration');
    assert.match(f.html, /Bài SEO/);
    assert.equal(f.calls.length, 0);
    await f.state.openSeo();
    assert.equal(f.state.tab.value, 'seo');
    assert.equal(f.state.seo.value.title, 'Password SEO');
    assert.deepEqual(f.calls, [{ seoCode: 'game_password' }]);
    f.state.seo.value.title = 'Unsaved draft';
    f.state.tab.value = 'configuration';
    await f.state.openSeo();
    assert.equal(f.state.seo.value.title, 'Unsaved draft');
    assert.equal(f.calls.length, 1);
});

test('admin rejects negative fractional and excessive prices without submitting', async () => {
    const f = await screen('configuration');
    for (const value of [-1, 0.5, 100000001]) {
        f.state.price.value = value;
        await f.state.save();
    }
    assert.equal(f.calls.length, 0);
    f.state.price.value = 1500;
    await f.state.save();
    assert.deepEqual(f.calls[0], { price: 1500 });
});

test('failed configuration preserves drafts and history escapes messages while resolving only after confirmation', async () => {
    const f = await screen('configuration', {
        configure: async () => {
            throw new Error('offline');
        },
    });
    f.state.price.value = 100;
    await f.state.save();
    assert.equal(f.state.price.value, 100);
    assert.equal(f.state.saving.value, false);
    const history = await screen('history');
    assert.ok(history.html.includes('&lt;script&gt;bad&lt;/script&gt;'));
    assert.ok(!history.html.includes('<script>bad</script>'));
    history.state.q.value = 'user';
    history.state.status.value = 'needs_review';
    await history.state.load(2);
    assert.deepEqual(history.calls.at(-1), { page: 2, q: 'user', status: 'needs_review' });
    await history.state.resolve(1, 'error');
    assert.ok(history.calls.some((call) => call.id === 1 && call.status === 'error'));
    assert.equal(history.state.resolving.value, null);
});

test('admin cleanup requires confirmation and reports bounded deletion results', async () => {
    const f = await screen('history');
    assert.match(f.html, /Dọn dữ liệu đổi MK cũ/);
    await f.state.clearHistory();
    assert.ok(f.calls.some((call) => call.clear === 30));
    assert.match(f.state.cleanupMessage.value, /10.*Còn dữ liệu/);
    assert.equal(f.state.clearing.value, false);
    const cancelled = await screen('history', {}, false);
    await cancelled.state.clearHistory();
    assert.ok(!cancelled.calls.some((call) => 'clear' in call));
});

function selectableHistory(params) {
    return {
        data: ['success', 'error', 'processing'].map((status, index) => ({
            id: index + 1,
            username: `account-${index}`,
            status,
            can_retry: status === 'error',
            has_saved_credentials: true,
            created_at: '2026-11-17T00:00:00Z',
            completed_at: status === 'processing' ? null : '2026-11-17T00:00:00Z',
        })),
        meta: { current_page: params.page, last_page: 2, total: 3 },
    };
}

test('ID selection includes terminal rows only and deletion sends the confirmed ids', async () => {
    const f = await screen('history', { history: async (params) => selectableHistory(params) });
    assert.match(f.html, /Chọn đơn #1/);
    assert.match(f.html, /Xóa đã chọn/);
    await f.state.deleteSelected();
    assert.equal(f.calls.length, 0);
    f.state.toggleAll({ target: { checked: true } });
    assert.deepEqual([...f.state.selectedIds.value], [1, 2]);
    assert.equal(f.state.allSelected.value, true);
    f.state.rows.value[1].status = 'processing';
    f.state.syncSelection();
    assert.deepEqual([...f.state.selectedIds.value], [1]);
    await f.state.deleteSelected();
    assert.deepEqual(f.calls[0], { deletedIds: [1] });
    assert.deepEqual([...f.state.selectedIds.value], []);
    assert.match(f.state.cleanupMessage.value, /Đã xóa 1 đơn/);
    assert.equal(f.state.clearing.value, false);
});

test('ID deletion cancellation preserves records and failed requests preserve selection', async () => {
    const cancelled = await screen('history', { history: async (params) => selectableHistory(params) }, false);
    cancelled.state.toggleAll({ target: { checked: true } });
    await cancelled.state.deleteSelected();
    assert.equal(cancelled.calls.length, 0);
    assert.deepEqual([...cancelled.state.selectedIds.value], [1, 2]);
    const failed = await screen('history', {
        history: async (params) => selectableHistory(params),
        clearSelected: async () => {
            throw new Error('offline');
        },
    });
    failed.state.toggleAll({ target: { checked: true } });
    await failed.state.deleteSelected();
    assert.equal(failed.state.clearing.value, false);
    assert.deepEqual([...failed.state.selectedIds.value], [1, 2]);
    failed.state.toggleAll({ target: { checked: false } });
    assert.deepEqual([...failed.state.selectedIds.value], []);
});

test('moving to another history page drops selections from the previous page', async () => {
    const f = await screen('history', {
        history: async (params) => {
            const result = selectableHistory(params);
            if (params.page === 2) result.data = result.data.map((row) => ({ ...row, id: row.id + 20 }));
            return result;
        },
    });
    f.state.toggleAll({ target: { checked: true } });
    await f.state.load(2);
    assert.deepEqual([...f.state.selectedIds.value], []);
});

test('admin realtime follows applied filters and ignores packets from closed streams', async () => {
    const originalWindow = globalThis.window;
    const originalDocument = globalThis.document;
    const streams = [];
    globalThis.window = {
        EventSource: class {
            events = {};
            constructor(url) {
                this.url = url;
                streams.push(this);
            }
            addEventListener(name, callback) {
                this.events[name] = callback;
            }
            close() {
                this.closed = true;
            }
        },
    };
    globalThis.document = { hidden: false };
    try {
        const f = await screen('history');
        streams[0].events.orders({
            data: JSON.stringify({ data: [{ id: 2, status: 'processing' }], meta: { current_page: 1, last_page: 1, total: 1 } }),
        });
        assert.equal(f.state.rows.value[0].status, 'processing');
        f.state.status.value = 'success';
        await f.state.load(2);
        assert.match(streams[1].url, /page=2.*status=success/);
        assert.equal(streams[0].closed, true);
        streams[0].events.orders({ data: JSON.stringify({ data: [{ id: 999 }], meta: {} }) });
        assert.notEqual(f.state.rows.value[0].id, 999);
        globalThis.document.hidden = true;
        f.state.visibilityChanged();
        assert.equal(streams[1].closed, true);
        f.state.closeStream();
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
        if (originalDocument === undefined) delete globalThis.document;
        else globalThis.document = originalDocument;
    }
});

test('admin requeues using stored credentials and confirmation without asking for passwords', async () => {
    const f = await screen('history', { history: async (params) => selectableHistory(params) });
    assert.match(f.html, /Đổi lại miễn phí/);
    await f.state.retry({ id: 42, status: 'error', can_retry: true, has_saved_credentials: true, attempt_version: 3 });
    assert.deepEqual(f.calls.find((call) => call.retry === 42).data, { attempt_version: 3 });
    assert.equal(f.state.retrying.value, null);
    assert.ok(!f.html.includes('new-secret'));
    const cancelled = await screen('history', {}, false);
    await cancelled.state.retry({ id: 42, status: 'error', can_retry: true, has_saved_credentials: true, attempt_version: 3 });
    assert.ok(!cancelled.calls.some((call) => call.retry));
    const missing = await screen('history');
    await missing.state.retry({ id: 42, status: 'error', can_retry: true, has_saved_credentials: false, attempt_version: 3 });
    assert.ok(!missing.calls.some((call) => call.retry));
});

test('admin navigation exposes configuration and password history', () => {
    for (const file of ['../../resources/js/layouts/admin/sidebar/navigation.ts', '../../resources/js/router/modules/admin/index.ts']) {
        const source = readFileSync(new URL(file, import.meta.url), 'utf8');
        assert.ok(source.includes('game-password/configuration'));
        assert.ok(source.includes('game-password/history'));
    }
});
