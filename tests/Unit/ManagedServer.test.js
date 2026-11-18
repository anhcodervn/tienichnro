import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';
import * as vue from 'vue';

const require = createRequire(import.meta.url);
async function page(type = 'website') {
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
        configuration: async () => ({ credentials_saved: true, endpoint: 'https://napcarot.com/api/v1/catalog' }),
        configure: async (data) => {
            calls.push({ credentials: data });
            return { credentials_saved: true, endpoint: 'https://napcarot.com/api/v1/catalog' };
        },
        save: async (kind, draft, id) => {
            calls.push({ save: { kind, draft, id } });
        },
        remove: async (kind, id) => {
            calls.push({ remove: { kind, id } });
        },
        sync: async () => {
            calls.push({ sync: true });
            return { count: 19 };
        },
    };
    const source = readFileSync(new URL('../../resources/js/pages/admin/managed-servers/index.vue', import.meta.url), 'utf8').replace(
        '</script>',
        'defineExpose({ load, loadConfiguration, save, edit, remove, draft, editingId, q, active, apiKey, apiSecret, saveCredentials, sync });\n</script>',
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
                serverTitles: { carot: 'Server nạp Carot', website: 'Server login website', game: 'Server login game' },
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
        await state.loadConfiguration();
        return render;
    };
    const html = await renderToString(vue.createSSRApp(exports.default, { type }));
    return { state, calls, html };
}

for (const type of ['carot', 'website', 'game']) {
    test(`server ${type} page isolates its API calls and paginates filters`, async () => {
        const { state, calls, html } = await page(type);
        assert.equal(html.includes('Kết nối Nạp Carot'), type === 'carot');
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

test('NapCarot configuration masks credentials and sends them only on save before explicit sync', async () => {
    const { state, calls, html } = await page('carot');
    assert.ok(html.includes('••••••'));
    assert.ok(html.includes('type="password"'));
    assert.ok(!calls.some((call) => call.credentials || call.sync));
    state.apiKey.value = 'ak_private';
    state.apiSecret.value = 'sk_private';
    await state.saveCredentials();
    assert.deepEqual(calls.find((call) => call.credentials).credentials, { api_key: 'ak_private', api_secret: 'sk_private' });
    assert.equal(state.apiKey.value, '');
    assert.equal(state.apiSecret.value, '');
    await state.sync();
    assert.ok(calls.some((call) => call.sync));
});
