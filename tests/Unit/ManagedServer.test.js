import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';
import * as vue from 'vue';

const require = createRequire(import.meta.url);
async function page(type = 'web') {
    const calls = [];
    const service = {
        list: async (kind, params) => {
            calls.push({ kind, ...params });
            return {
                data: [{ id: 1, type: kind, server_id: '4', name: '<script>unsafe</script>', is_active: true, sort_order: 0 }],
                current_page: params.page,
                last_page: 2,
                total: 21,
            };
        },
        save: async (kind, draft, id) => {
            calls.push({ save: { kind, draft, id } });
        },
        remove: async (kind, id) => {
            calls.push({ remove: { kind, id } });
        },
    };
    const source = readFileSync(new URL('../../resources/js/pages/admin/managed-servers/index.vue', import.meta.url), 'utf8').replace(
        '</script>',
        'defineExpose({ load, save, edit, remove, draft, editingId, q, active });\n</script>',
    );
    const { descriptor } = parse(source);
    const script = compileScript(descriptor, { id: 'managed-servers', inlineTemplate: true });
    const code = ts.transpileModule(script.content, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const exports = {};
    new Function('require', 'exports', code)((name) => {
        if (name === '@/services/managed-server.service')
            return {
                managedServerService: service,
                serverTitles: { napcarot: 'Server nạp Carot', web: 'Server login website', game: 'Server login game' },
            };
        if (name === '@/utils/response')
            return {
                handleErrorResponse: (error) => {
                    throw error;
                },
                handleSuccessResponse() {},
            };
        if (name === 'sweetalert2') return { default: { fire: async () => ({ isConfirmed: true }) } };
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
    const html = await renderToString(vue.createSSRApp(exports.default, { type }));
    return { state, calls, html };
}

for (const type of ['napcarot', 'web', 'game']) {
    test(`server ${type} page isolates its API calls and paginates filters`, async () => {
        const { state, calls } = await page(type);
        state.q.value = '4';
        state.active.value = '0';
        await state.load(2);
        assert.deepEqual(calls.at(-1), { kind: type, q: '4', active: '0', page: 2 });
        state.edit({ id: 1, server_id: '4', name: '4 sao', is_active: false, sort_order: 9 });
        await state.save();
        assert.deepEqual(calls.find((call) => call.save).save, {
            kind: type,
            id: 1,
            draft: { server_id: '4', name: '4 sao', is_active: false, sort_order: 9 },
        });
        assert.equal(state.editingId.value, null);
        await state.remove({ id: 1, name: '4 sao' });
        assert.deepEqual(calls.find((call) => call.remove).remove, { kind: type, id: 1 });
    });
}
