import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';
import * as vue from 'vue';

const require = createRequire(import.meta.url);
const navigation = {};
const source = readFileSync(new URL('../../resources/js/layouts/admin/sidebar/navigation.ts', import.meta.url), 'utf8');
new Function('require', 'exports', ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText)(
    require,
    navigation,
);

test('each management destination appears once in a named section and resolves its most specific title', () => {
    const hrefs = navigation.adminMenuGroups.flatMap((group) => [group.href, ...(group.children || []).map((child) => child.href)]).filter(Boolean);
    assert.equal(new Set(hrefs).size, hrefs.length);
    const sections = new Set(navigation.adminMenuSections.map((section) => section.key));
    for (const group of navigation.adminMenuGroups.filter((item) => item.key !== 'overview')) assert.ok(sections.has(group.section));
    assert.equal(navigation.resolveAdminNavigation('/admin/wallets/token-captcha').href, '/admin/wallets/token-captcha');
    assert.equal(navigation.resolveAdminNavigation('/admin/licenses/products/5/edit').href, '/admin/licenses/products');
    assert.equal(navigation.resolveAdminNavigation('/admin').href, '/admin');
    assert.equal(navigation.resolveAdminNavigation('/admin/unknown'), null);
});

test('navigation search supports accents and filters child destinations without mutating the menu', () => {
    const before = JSON.stringify(navigation.adminMenuGroups);
    assert.equal(navigation.filterAdminMenu('  nguoi DUNG ')[0].key, 'users');
    const wallet = navigation.filterAdminMenu('TOKEN')[0];
    assert.equal(wallet.key, 'wallet');
    assert.deepEqual(
        wallet.children.map((child) => child.href),
        ['/admin/wallets/token-captcha'],
    );
    assert.ok(navigation.filterAdminMenu('hang doi').some((group) => group.key === 'queues'));
    assert.equal(navigation.filterAdminMenu('no-such-menu').length, 0);
    assert.equal(navigation.filterAdminMenu(' ').length, navigation.adminMenuGroups.length);
    assert.equal(JSON.stringify(navigation.adminMenuGroups), before);
});

test('overview renders all management destinations and is the default admin page', async () => {
    const overviewSource = readFileSync(new URL('../../resources/js/pages/admin/overview/index.vue', import.meta.url), 'utf8');
    const { descriptor } = parse(overviewSource);
    const compiled = compileScript(descriptor, { id: 'admin-overview-test', inlineTemplate: true });
    const code = ts.transpileModule(compiled.content, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
    const component = {};
    new Function('require', 'exports', code)((name) => {
        if (name === '@/layouts/admin/sidebar/navigation') return navigation;
        if (name === 'vue-router')
            return {
                RouterLink: {
                    props: ['to'],
                    setup:
                        (props, { slots }) =>
                        () =>
                            vue.h('a', { href: props.to }, slots.default?.()),
                },
            };
        return require(name);
    }, component);
    const html = await renderToString(vue.createSSRApp(component.default));
    assert.ok(html.includes('Tổng quan quản trị'));
    for (const group of navigation.adminMenuGroups.filter((item) => item.section)) {
        for (const href of [group.href, ...(group.children || []).map((child) => child.href)].filter(Boolean)) {
            assert.ok(html.includes('href="' + href + '"'), 'Missing destination: ' + href);
        }
    }
    const routerSource = readFileSync(new URL('../../resources/js/router/modules/admin/index.ts', import.meta.url), 'utf8');
    const routerModule = {};
    new Function('exports', ts.transpileModule(routerSource, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText)(routerModule);
    const rootRoute = routerModule.default.children.find((route) => route.path === '');
    assert.equal(rootRoute.name, 'admin.overview');
    assert.equal(rootRoute.redirect, undefined);
    assert.equal(typeof rootRoute.component, 'function');
});

test('mobile navigation restores scrolling on close, route changes and unmount, and keeps desktop sidebar open', async () => {
    const mounted = [];
    const unmounted = [];
    const classes = new Set();
    const events = {};
    const documentBefore = globalThis.document;
    const windowBefore = globalThis.window;
    const viewport = {
        matches: false,
        addEventListener: (key, handler) => {
            events[key] = handler;
        },
        removeEventListener: (key) => {
            delete events[key];
        },
    };
    globalThis.document = {
        body: { style: { overflow: 'auto' }, classList: { add: (value) => classes.add(value), remove: (value) => classes.delete(value) } },
    };
    globalThis.window = {
        matchMedia: () => viewport,
        addEventListener: (key, handler) => {
            events[key] = handler;
        },
        removeEventListener: (key) => {
            delete events[key];
        },
    };
    const route = vue.reactive({ path: '/admin', fullPath: '/admin' });
    const componentSource = readFileSync(new URL('../../resources/js/layouts/AdminLayout.vue', import.meta.url), 'utf8').replace(
        '</script>',
        'defineExpose({ isMobile, isSidebarOpen });\n</script>',
    );
    const { descriptor } = parse(componentSource);
    const script = compileScript(descriptor, { id: 'admin-shell-test' });
    const module = {};
    const code = ts.transpileModule(script.content, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    new Function('require', 'exports', code)((name) => {
        if (name === 'vue') return { ...vue, onMounted: (callback) => mounted.push(callback), onUnmounted: (callback) => unmounted.push(callback) };
        if (name === 'vue-router') return { useRoute: () => route };
        if (name.endsWith('/navigation')) return navigation;
        return { default: {} };
    }, module);
    let state;
    const scope = vue.effectScope();
    try {
        scope.run(() =>
            module.default.setup(
                {},
                {
                    expose: (value) => {
                        state = value;
                    },
                },
            ),
        );
        mounted.forEach((callback) => callback());
        await vue.nextTick();
        assert.equal(state.isMobile.value, true);
        assert.equal(state.isSidebarOpen.value, false);
        state.isSidebarOpen.value = true;
        await vue.nextTick();
        assert.equal(document.body.style.overflow, 'hidden');
        events.keydown({ key: 'Escape' });
        await vue.nextTick();
        assert.equal(document.body.style.overflow, 'auto');
        state.isSidebarOpen.value = true;
        await vue.nextTick();
        route.fullPath = '/admin/users';
        await vue.nextTick();
        assert.equal(state.isSidebarOpen.value, false);
        viewport.matches = true;
        events.change();
        await vue.nextTick();
        assert.equal(state.isMobile.value, false);
        assert.equal(state.isSidebarOpen.value, true);
        assert.equal(document.body.style.overflow, 'auto');
        unmounted.forEach((callback) => callback());
        assert.equal(classes.has('admin-ui'), false);
        assert.equal(events.change, undefined);
        assert.equal(events.keydown, undefined);
    } finally {
        scope.stop();
        globalThis.document = documentBefore;
        globalThis.window = windowBefore;
    }
});
