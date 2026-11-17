import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';
import * as vue from 'vue';

const require = createRequire(import.meta.url);
async function page(kind) {
    const calls = [];
    const source = readFileSync(new URL('../../resources/js/pages/admin/wallets/history.vue', import.meta.url), 'utf8').replace(
        '</script>',
        'defineExpose({ load, reset, q, direction, from, to });\n</script>',
    );
    const { descriptor } = parse(source);
    const script = compileScript(descriptor, { id: 'wallet-history', inlineTemplate: true });
    const code = ts.transpileModule(script.content, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const exports = {};
    new Function('require', 'exports', code)((name) => {
        if (name === '@/config/axios')
            return {
                default: {
                    get: async (url, options) => {
                        calls.push({ url, ...options.params });
                        return {
                            data: {
                                data: [
                                    {
                                        id: 1,
                                        user_id: 2,
                                        username: '<script>unsafe</script>',
                                        amount: 1000,
                                        direction: 'credit',
                                        balance_before: 0,
                                        balance_after: 1000,
                                        description: 'refund',
                                        created_at: '2026-11-17T00:00:00Z',
                                    },
                                ],
                                current_page: options.params.page,
                                last_page: 2,
                                total: 21,
                            },
                        };
                    },
                },
            };
        if (name === '@/utils/response')
            return {
                handleErrorResponse: (error) => {
                    throw error;
                },
            };
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
        if (name.startsWith('@/components/')) return { default: { props: ['title'], setup: (props) => () => vue.h('h1', props.title) } };
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
    const html = await renderToString(vue.createSSRApp(exports.default, { kind }));
    return { state, calls, html };
}

for (const kind of ['money', 'token']) {
    test(`ledger ${kind} renders safely and sends filters and pagination to the server`, async () => {
        const { state, calls, html } = await page(kind);
        assert.ok(html.includes(kind === 'money' ? 'Lịch sử dòng tiền' : 'Lịch sử token'));
        assert.ok(html.includes('&lt;script&gt;unsafe&lt;/script&gt;'));
        assert.ok(html.includes('20 dòng/trang'));
        state.q.value = 'refund';
        state.direction.value = 'credit';
        state.from.value = state.to.value = '2026-11-17';
        await state.load(2);
        assert.deepEqual(calls.at(-1), {
            url: '/api/admin-api/wallets/history',
            kind,
            page: 2,
            q: 'refund',
            direction: 'credit',
            from: '2026-11-17',
            to: '2026-11-17',
        });
        state.reset();
        assert.equal(calls.at(-1).page, 1);
        assert.equal(calls.at(-1).q, '');
    });
}
