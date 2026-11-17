import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';
import * as vue from 'vue';

const require = createRequire(import.meta.url);
async function page(section = 'configuration', overrides = {}) {
    const calls = [];
    const errors = [];
    const bank = { bank_id: 25, bank_name: 'MB', account_number: '123456', account_name: 'TEST' };
    const config = {
        enabled: true,
        bank_id: 25,
        bank,
        credentials_saved: true,
        cron_key_saved: true,
        webhook_secret_saved: true,
        webhook_url: 'https://example.test/api/recharge/callbacks/apibankvn',
        cron_url: 'https://example.test/api/recharge/cron/check',
        last_check: null,
    };
    const service = {
        configuration: async () => config,
        save: async (data) => {
            calls.push({ save: data });
            return config;
        },
        banks: async (data) => {
            calls.push({ banks: data });
            return [bank];
        },
        check: async () => ({ checked: 2, credited: 1, errors: 0, at: '2026-11-17T00:00:00Z' }),
        history: async (params) => {
            calls.push({ history: params });
            return {
                data: [
                    {
                        id: 1,
                        code: 'TN123',
                        amount: 10000,
                        status: 'paid',
                        bank_name: 'MB',
                        account_number: '123456',
                        user: { username: '<script>bad</script>' },
                        created_at: '2026-11-17T00:00:00Z',
                    },
                ],
                current_page: params.page,
                last_page: 2,
                total: 21,
            };
        },
        ...overrides,
    };
    const source = readFileSync(new URL('../../resources/js/pages/admin/recharge/index.vue', import.meta.url), 'utf8').replace(
        '</script>',
        'defineExpose({ loadConfiguration, load, loadBanks, save, check, apiKey, apiSecret, cronKey, webhookSecret, bankId, enabled, configuration, saving });\n</script>',
    );
    const { descriptor } = parse(source);
    const script = compileScript(descriptor, { id: 'admin-recharge-test', inlineTemplate: true });
    const code = ts.transpileModule(script.content, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const exports = {};
    new Function('require', 'exports', code)((name) => {
        if (name === '@/services/admin-recharge.service') return { adminRechargeService: service, rechargeStatuses: { paid: 'Đã cộng tiền' } };
        if (name === '@/utils/response') return { handleErrorResponse: (error) => errors.push(error), handleSuccessResponse() {} };
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
        if (name.startsWith('@/components/')) return { default: { setup: () => () => vue.h('div') } };
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
        if (section === 'configuration') await state.loadConfiguration();
        else await state.load();
        return render;
    };
    const html = await renderToString(vue.createSSRApp(exports.default, { section }));
    return { state, calls, errors, html };
}

test('admin recharge shows a POST cron request and masked saved credentials without fetching provider data on load', async () => {
    const result = await page();
    assert.ok(result.html.includes('X-Cron-Key: &lt;KHOA_CRON&gt;'));
    assert.ok(result.html.includes('curl -X POST'));
    assert.ok(result.html.includes('Đã lưu'));
    assert.equal(result.calls.length, 0);
    await result.state.save();
    assert.deepEqual(result.calls[0], { save: { enabled: true, bank_id: 25 } });
});

test('draft credentials verify banks without saving and are cleared after a successful save', async () => {
    const result = await page();
    result.state.apiKey.value = 'new-key';
    result.state.apiSecret.value = 'new-secret';
    result.state.cronKey.value = 'z'.repeat(40);
    result.state.webhookSecret.value = 'w'.repeat(40);
    await result.state.loadBanks();
    assert.deepEqual(result.calls[0], { banks: { api_key: 'new-key', api_secret: 'new-secret' } });
    await result.state.save();
    assert.equal(result.calls[1].save.cron_key, 'z'.repeat(40));
    assert.equal(result.calls[1].save.webhook_secret, 'w'.repeat(40));
    assert.equal(result.state.webhookSecret.value, '');
    assert.equal(result.state.apiSecret.value, '');
    assert.equal(result.state.cronKey.value, '');
});

test('failed configuration saves keep the draft and release the save button', async () => {
    const result = await page('configuration', {
        save: async () => {
            throw new Error('Bank unavailable');
        },
    });
    result.state.apiSecret.value = 'draft-secret';
    await result.state.save();
    assert.equal(result.state.apiSecret.value, 'draft-secret');
    assert.equal(result.state.saving.value, false);
    assert.equal(result.errors[0].message, 'Bank unavailable');
});

test('recharge history escapes bank user data and forwards pagination', async () => {
    const result = await page('history');
    assert.ok(result.html.includes('&lt;script&gt;bad&lt;/script&gt;'));
    assert.ok(!result.html.includes('<script>bad</script>'));
    await result.state.load(2);
    assert.equal(result.calls.at(-1).history.page, 2);
});
