import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';
import { createSSRApp, h } from 'vue';
import * as vue from 'vue';

const require = createRequire(import.meta.url);
const account = { id: 7, username: 'player7', name: 'Player Seven', email: 'player@example.com', full_name: 'Player Seven', role: 'user', status: 'active', wallet_balance: 125000, phone: null, avatar: null, email_verified_at: null, created_at: null, last_login_at: null };

function environment(query = {}, overrides = {}) {
    const calls = [];
    const service = {
        show: async () => ({ ...account }),
        list: async () => ({ data: [{ ...account }], meta: { last_page: 1 } }),
        walletHistory: async () => ({ data: [], current_page: 1, last_page: 1 }),
        tools: async () => ({ tools: [{ id: 1, name: 'Mod tool', product_code: 'MOD_TOOL', image_url: '/storage/uploads/mod.webp', latest_version: '2.0.0', is_active: true }, { id: 2, name: 'Disabled tool', product_code: 'DISABLED', is_active: false }], product_ids: [2] }),
        updateTools: async (id, productIds) => { calls.push(['tools', id, [...productIds]]); return { product_ids: productIds }; },
        update: async (id, payload) => { calls.push(['profile', id, { ...payload }]); return { ...account, ...payload }; },
        adjustWallet: async (id, payload) => { calls.push(['wallet', id, { ...payload }]); return { balance: 130000 }; },
        resetPassword: async (id, payload) => { calls.push(['password', id, { ...payload }]); },
        ...overrides,
    };
    const alerts = [];
    const popup = { fire: async (options) => { alerts.push(options); return { isConfirmed: true }; } };
    const route = { params: { user_id: '7' }, query };
    let controls;
    function compile(path, exposed) {
        const source = readFileSync(new URL(`../../resources/js/pages/admin/users/${path}/index.vue`, import.meta.url), 'utf8')
            .replace('</script>', `defineExpose({${exposed}});\n</script>`);
        const { descriptor } = parse(source);
        const compiled = compileScript(descriptor, { id: 'admin-user-test', inlineTemplate: true });
        const code = ts.transpileModule(compiled.content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;
        const module = {};
        new Function('require', 'exports', code)((name) => {
            if (name === 'vue') return { ...vue, watch: () => {}, onMounted: () => {} };
            if (name === '@/services/admin-user.service') return { adminUserService: service };
            if (name === '@/utils/response') return { handleErrorResponse() {}, handleSuccessResponse() {} };
            if (name === 'sweetalert2') return { default: popup };
            if (name === 'vue-router') return { useRoute: () => route, RouterLink: { props: ['to'], setup: (props, { slots }) => () => h('a', { href: typeof props.to === 'string' ? props.to : `${props.to.path}?${new URLSearchParams(props.to.query)}` }, slots.default?.()) } };
            return require(name);
        }, module);
        return module.default;
    }
    const detail = () => compile('info', 'load, historyLoad, loadTools, saveTools, tools, selectedTools, toolsFailed, toolsLoading, toolsLoadedFor, saveProfile, adjustWallet, resetPassword, user, profile, wallet, password, busy, errors');
    return {
        calls, alerts, popup,
        async actions() {
            detail().setup({}, { expose: (value) => { controls = value; } });
            await controls.load();
            return controls;
        },
        async render(path = 'info') {
            const component = path === 'lists' ? compile('lists', 'load') : detail();
            const setup = component.setup;
            component.setup = async (props, context) => {
                const render = setup(props, { ...context, expose: (value) => { controls = value; } });
                await controls.load();
                if (controls.historyLoad) await controls.historyLoad();
                return render;
            };
            return renderToString(createSSRApp(component));
        },
    };
}

test('user list exposes information credit debit and password actions for each user', async () => {
    const html = await environment().render('lists');
    for (const label of ['Thông tin user', 'Cộng tiền', 'Trừ tiền', 'Lấy lại mật khẩu']) assert.ok(html.includes(label));
    assert.ok(html.includes('/admin/users/7?tab=wallet&amp;direction=debit'));
    assert.ok(html.includes('/admin/users/7?tab=password'));
    assert.ok(html.includes('/admin/users/7?tab=tools'));
});

test('detail tabs render editable profile wallet history and password forms', async () => {
    const profile = await environment().render();
    assert.ok(profile.includes('Lưu thông tin'));
    assert.ok(profile.includes('player@example.com'));
    assert.ok(profile.includes('125.000 đ'));
    const wallet = await environment({ tab: 'wallet' }).render();
    assert.ok(wallet.includes('Lịch sử giao dịch ví'));
    assert.ok(wallet.includes('Lý do điều chỉnh'));
    const password = await environment({ tab: 'password' }).render();
    assert.ok(password.includes('Đặt lại mật khẩu'));
    assert.ok(password.includes('autocomplete="new-password"'));
});

test('saving information targets the selected account', async () => {
    const env = environment();
    const state = await env.actions();
    state.profile.full_name = 'Updated Name';
    await state.saveProfile();
    assert.equal(env.calls[0][0], 'profile');
    assert.equal(env.calls[0][1], 7);
    assert.equal(env.calls[0][2].full_name, 'Updated Name');
});

test('tools tab shows image name version and currently granted disabled tools', async () => {
    const html = await environment({ tab: 'tools' }).render();
    for (const text of ['Cấp quyền sử dụng tool', 'Mod tool', '2.0.0', '/storage/uploads/mod.webp', 'Tool đang tắt', 'Lưu quyền tool']) assert.ok(html.includes(text));
    assert.match(html, /type="checkbox" value="2"[^>]*checked/);
});

test('tool selection only saves on submit and targets the selected user', async () => {
    const env = environment({ tab: 'tools' });
    const state = await env.actions();
    assert.deepEqual(state.selectedTools.value, [2]);
    state.selectedTools.value = [1];
    assert.equal(env.calls.length, 0);
    await state.saveTools();
    assert.deepEqual(env.calls[0], ['tools', 7, [1]]);
});

test('failed tool permission load blocks submitting an empty permission list', async () => {
    const env = environment({ tab: 'tools' }, { tools: async () => { throw new Error('Network unavailable'); } });
    const state = await env.actions();
    assert.equal(state.toolsFailed.value, true);
    await state.saveTools();
    assert.equal(env.calls.length, 0);
});

test('wallet and password changes require confirmation before calling their APIs', async () => {
    const env = environment();
    const state = await env.actions();
    env.popup.fire = async () => ({ isConfirmed: false });
    state.wallet.amount = 5000;
    state.wallet.description = 'Credit test';
    await state.adjustWallet();
    await state.resetPassword();
    assert.equal(env.calls.length, 0);
    env.popup.fire = async () => ({ isConfirmed: true });
    await state.adjustWallet();
    assert.equal(env.calls[0][0], 'wallet');
    assert.equal(env.calls[0][1], 7);
    assert.equal(env.calls[0][2].amount, 5000);
    assert.match(env.calls[0][2].request_id, /^[a-f\d-]{36}$/i);
    assert.equal(state.user.value.wallet_balance, 130000);
    state.password.password = 'NewPassword123';
    state.password.password_confirmation = 'NewPassword123';
    await state.resetPassword();
    assert.equal(env.calls[1][0], 'password');
    assert.equal(env.calls[1][1], 7);
    assert.equal(state.password.password, '');
    assert.equal(state.password.password_confirmation, '');
});

test('wallet retry retains the same request id after a network failure', async () => {
    const ids = [];
    const env = environment({}, { adjustWallet: async (_, payload) => { ids.push(payload.request_id); throw new Error('Network unavailable'); } });
    const state = await env.actions();
    state.wallet.amount = 5000;
    state.wallet.description = 'Retry';
    await state.adjustWallet();
    await state.adjustWallet();
    assert.equal(ids.length, 2);
    assert.ok(ids[0]);
    assert.equal(ids[0], ids[1]);
});
