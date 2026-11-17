import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';
import * as vue from 'vue';

const require = createRequire(import.meta.url);
const statuses = { pending: 'Chờ ghép', processing: 'Đang chụp ảnh', success: 'Hoàn tất', error: 'Lỗi' };

async function environment(file, overrides = {}) {
    const calls = [];
    const errors = [];
    const notices = [];
    const exposed = file === 'configuration' ? 'load, save, price, saving, errorMessage, openSeo, seo, tab' : 'load, rows, q, status, page, total';
    const source = readFileSync(new URL(`../../resources/js/pages/admin/game-verification/${file}.vue`, import.meta.url), 'utf8').replace(
        '</script>',
        `defineExpose({${exposed}});\n</script>`,
    );
    const { descriptor } = parse(source);
    const script = compileScript(descriptor, { id: 'admin-game-verification-test', inlineTemplate: true });
    const code = ts.transpileModule(script.content, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const exports = {};
    new Function('require', 'exports', code)((name) => {
        if (name.includes('admin-service-management.service'))
            return {
                adminServiceManagement: {
                    get: async (code) => {
                        calls.push({ seoCode: code });
                        return { seo: { title: 'Image SEO', lock_version: 0 } };
                    },
                },
            };
        if (name.includes('ToolSeoEditor')) return { default: { props: ['code', 'modelValue'], setup: () => () => vue.h('div') } };
        if (name === '@/services/admin-game-verification.service')
            return {
                gameImageStatuses: statuses,
                adminGameVerificationService: {
                    configuration: async () => ({ price: 100 }),
                    configure: async (price) => {
                        calls.push(price);
                        return { price };
                    },
                    history: async (params) => {
                        calls.push(params);
                        return {
                            data: [
                                {
                                    id: 1,
                                    member: 'Member',
                                    user_id: 7,
                                    username: 'game-account',
                                    server: 'Server 1',
                                    status: 'error',
                                    error_message: '<script>bad</script>',
                                    charged_amount: 100,
                                    refunded_at: '2026-11-16T10:00:00Z',
                                    created_at: '2026-11-16T09:00:00Z',
                                    completed_at: '2026-11-16T10:00:00Z',
                                },
                            ],
                            meta: { current_page: params.page, last_page: 2, total: 22 },
                        };
                    },
                    ...overrides,
                },
            };
        if (name === '@/utils/response')
            return { handleErrorResponse: (error) => errors.push(error), handleSuccessResponse: (value) => notices.push(value) };
        if (name.includes('DataTable'))
            return {
                default: {
                    props: ['data'],
                    setup:
                        (props, { slots }) =>
                        () =>
                            vue.h(
                                'table',
                                props.data.map((row) =>
                                    vue.h(
                                        'tr',
                                        Object.values(slots).map((slot) => slot({ row })),
                                    ),
                                ),
                            ),
                },
            };
        if (name.startsWith('@/components/'))
            return {
                default: {
                    setup:
                        (_, { slots }) =>
                        () =>
                            vue.h('div', slots.default?.()),
                },
            };
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
        await state.load();
        return render;
    };
    const html = await renderToString(vue.createSSRApp(exports.default));
    return { state, html, calls, errors, notices };
}

test('verification configuration saves prices and loads SEO for account_verification', async () => {
    const env = await environment('configuration');
    env.state.price.value = 250;
    await env.state.save();
    assert.deepEqual(env.calls, [250]);
    await env.state.openSeo();
    assert.deepEqual(env.calls[1], { seoCode: 'account_verification' });
    assert.equal(env.state.tab.value, 'seo');
    assert.match(env.html, /SEO/);
});

test('verification history and menu render without exposing credentials', async () => {
    const env = await environment('history');
    assert.ok(!env.html.includes('<script>bad</script>'));
    const navigation = readFileSync(new URL('../../resources/js/layouts/admin/sidebar/navigation.ts', import.meta.url), 'utf8');
    assert.match(navigation, /game-verification\/configuration/);
    assert.match(navigation, /game-verification\/history/);
});
