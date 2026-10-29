import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';
import * as vue from 'vue';

const require = createRequire(import.meta.url);
const panel = { setup: (_, { slots }) => () => vue.h('div', slots.default?.()) };
const initialSeo = () => ({
    title: 'Hướng dẫn', excerpt: null, language: 'vi', content: [], faq: [],
    seo_title: 'SEO title', seo_description: null, meta_keywords: null, focus_keyword: null,
    cover_image: null, cover_alt: null, robots: 'index,follow', article_schema: true,
    breadcrumb_schema: true, is_published: false, lock_version: 3, updated_at: null,
    slug: 'tinh-tiem-nang', path: '/tinh-tiem-nang', canonical_url: 'https://example.com/tinh-tiem-nang', is_local: true,
});

function compile(file, exposed, dependencies) {
    const source = readFileSync(new URL('../../resources/js/pages/admin/tools/' + file, import.meta.url), 'utf8')
        .replace('</script>', 'defineExpose({' + exposed + '});\n</script>');
    const { descriptor } = parse(source);
    const script = compileScript(descriptor, { id: 'tool-seo-test', inlineTemplate: true });
    const code = ts.transpileModule(script.content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;
    const exports = {};
    new Function('require', 'exports', code)((name) => {
        if (name in dependencies) return dependencies[name];
        if (name.startsWith('@/components/') || name.startsWith('./Tool')) return { default: panel };
        return require(name);
    }, exports);
    return exports.default;
}

async function editorEnvironment(overrides = {}) {
    const calls = [];
    const notices = [];
    const component = compile('ToolSeoEditor.vue', 'form, save, editor, cover, busy, saving, errorMessage', {
        '@/services/admin-service-management.service': { adminServiceManagement: {
            updateSeo: async (code, payload) => {
                calls.push([code, structuredClone(payload)]);
                return { ...initialSeo(), ...payload, lock_version: 4 };
            },
            ...overrides,
        } },
        '@/utils/editor-image-upload': { uploadEditorImages: async content => content },
        '@/utils/response': { handleSuccessResponse: response => notices.push(response) },
    });
    let state;
    const setup = component.setup;
    component.setup = (props, context) => setup(props, { ...context, expose: value => { state = value; } });
    const html = await renderToString(vue.createSSRApp(component, { code: 'potential', modelValue: initialSeo() }));
    return { state, calls, notices, html };
}

test('SEO editor shows derived read-only location and the reused content editor workflow', async () => {
    const { html } = await editorEnvironment();
    assert.ok(html.includes('Slug theo liên kết công cụ'));
    assert.match(html, /value="tinh-tiem-nang"[^>]*readonly|readonly[^>]*value="tinh-tiem-nang"/);
    assert.ok(html.includes('https://example.com/tinh-tiem-nang'));
    assert.ok(html.includes('Nội dung bài viết'));
    assert.ok(html.includes('Thêm câu hỏi'));
    assert.ok(html.includes('Xuất bản bài SEO'));
});

test('saving flushes latest editor content and omits separately editable slugs and canonical URLs', async () => {
    const { state, calls } = await editorEnvironment();
    const fresh = [{ type: 'paragraph', children: [{ text: 'Nội dung mới nhất' }] }];
    state.editor.value = { flush: () => fresh, isUploadingImage: false };
    state.form.value.faq = [{ question: '  Cách dùng? ', answer: ' Nhập chỉ số. ' }, { question: '', answer: '' }];
    await state.save();
    assert.equal(calls.length, 1);
    const [code, payload] = calls[0];
    assert.equal(code, 'potential');
    assert.deepEqual(payload.content, fresh);
    assert.deepEqual(payload.faq, [{ question: 'Cách dùng?', answer: 'Nhập chỉ số.' }]);
    assert.equal(payload.expected_version, 3);
    assert.equal(payload.is_published, false);
    for (const field of ['slug', 'canonical_url', 'path', 'is_local', 'seo_category_id', 'type']) assert.equal(field in payload, false);
    assert.equal(state.form.value.lock_version, 4);
});

test('image uploads prevent saving and failed saves retain the draft', async () => {
    const { state, calls } = await editorEnvironment();
    state.cover.value = { loading: true };
    await state.save();
    assert.equal(calls.length, 0);
    state.cover.value.loading = false;
    state.editor.value = { isUploadingImage: true };
    await state.save();
    assert.equal(calls.length, 0);
    const error = await editorEnvironment({ updateSeo: async () => { throw { response: { data: { message: 'Phiên bản đã thay đổi' } } }; } });
    error.state.form.value.title = 'Bản nháp đang soạn';
    await error.state.save();
    assert.equal(error.state.form.value.title, 'Bản nháp đang soạn');
    assert.equal(error.state.errorMessage.value, 'Phiên bản đã thay đổi');
    assert.equal(error.state.saving.value, false);
});

async function pageEnvironment(editing = true) {
    const replacements = [];
    const initial = { code: 'potential', name: 'Tiềm năng', sort_order: 1 };
    let getCalls = 0;
    const component = compile('edit.vue', 'draft, seo, load, save, tab, configUploading', {
        vue: { ...vue, watch: () => {} },
        'vue-router': {
            RouterLink: panel, useRoute: () => ({ params: editing ? { code: 'potential' } : {}, query: {} }),
            useRouter: () => ({ replace: async target => replacements.push(target) }),
        },
        '@/services/admin-service-management.service': { adminServiceManagement: {
            list: async () => [{ sort_order: 7 }],
            get: async () => ({ service: initial, seo: { ...initialSeo(), slug: ++getCalls > 1 ? 'duong-dan-moi' : 'tinh-tiem-nang' } }),
            update: async value => value,
            create: async value => ({ ...value, code: 'created-tool' }),
        } },
        '@/utils/response': { handleSuccessResponse() {} },
    });
    let state;
    const setup = component.setup;
    component.setup = async (props, context) => {
        const render = setup(props, { ...context, expose: value => { state = value; } });
        await state.load();
        return render;
    };
    const html = await renderToString(vue.createSSRApp(component));
    return { state, html, replacements };
}

test('configuration save updates derived location without destroying an unsaved SEO draft', async () => {
    const { state, html } = await pageEnvironment();
    assert.ok(html.includes('role="tablist"'));
    assert.ok(html.includes('Bài SEO'));
    state.seo.value.title = 'Bài đang soạn';
    state.seo.value.content = [{ type: 'paragraph', children: [{ text: 'Chưa lưu' }] }];
    await state.save();
    assert.equal(state.seo.value.title, 'Bài đang soạn');
    assert.equal(state.seo.value.slug, 'duong-dan-moi');
    assert.equal(state.seo.value.lock_version, 3);
    assert.equal(state.seo.value.content[0].children[0].text, 'Chưa lưu');
});

test('creating a tool opens the SEO tab for its newly assigned stable code', async () => {
    const { state, replacements } = await pageEnvironment(false);
    assert.equal(state.draft.value.sort_order, 8);
    await state.save();
    assert.deepEqual(replacements, [{ name: 'admin.tools.edit', params: { code: 'created-tool' }, query: { tab: 'seo' } }]);
});
