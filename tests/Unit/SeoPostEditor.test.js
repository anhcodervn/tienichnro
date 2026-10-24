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
globalThis.window = { location: { origin: 'https://example.com' } };
const helpers = {};
const helperCode = ts.transpileModule(readFileSync(new URL('../../resources/js/pages/admin/seo/posts/create/form.ts', import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
new Function('exports', helperCode)(helpers);
const original = { ...helpers.newSeoPostForm(), id: 5, lock_version: 7, title: 'Nội dung gốc', seo_title: 'SEO mới nhất', seo_description: 'Mô tả SEO mới nhất', slug: 'bai-moi', seo_category_id: 2, content: [{ type: 'paragraph', children: [{ text: 'Body' }] }] };

function environment(id = 5, overrides = {}) {
    const calls = [];
    const replacements = [];
    const notices = [];
    const route = { params: id ? { seo_post_id: String(id) } : {} };
    const service = {
        postOptions: async () => ({ categories: [{ id: 2, name: 'Kiến thức', slug: 'kien-thuc' }] }),
        getPost: async () => structuredClone(original),
        revisions: async () => [],
        revision: async (postId, revisionId) => { calls.push(['revision', postId, revisionId]); return { snapshot: { slug: 'ban-cu', content: [], seo_title: null, seo_description: null, article_schema: false, breadcrumb_schema: false } }; },
        createPost: async (payload) => { calls.push(['create', JSON.parse(JSON.stringify(payload))]); return { data: { status: true, data: { ...payload, id: 32, lock_version: 1 } } }; },
        updatePost: async (postId, payload) => { calls.push(['update', postId, JSON.parse(JSON.stringify(payload))]); return { data: { status: true, data: { ...payload, id: postId, lock_version: 8 } } }; },
        ...overrides,
    };
    const exposed = 'form, load, handleSave, loadHistory, restoreRevision, closeHistory, applySnapshot, currentVersion, notice, historyOpen, historyError, historyLoading, revisions, contentEditor, coverUploader, errorMessage, busy, saving, generatedCanonicalUrl';
    const source = readFileSync(new URL('../../resources/js/pages/admin/seo/posts/create/index.vue', import.meta.url), 'utf8').replace('</script>', `defineExpose({${exposed}});\n</script>`);
    const { descriptor } = parse(source);
    const compiled = compileScript(descriptor, { id: 'seo-editor-test', inlineTemplate: true });
    const code = ts.transpileModule(compiled.content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;
    const module = {};
    const panel = { setup: (_, { slots }) => () => h('div', slots.default?.()) };
    new Function('require', 'exports', code)((name) => {
        if (name === 'vue') return { ...vue, watch: () => {} };
        if (name === './form') return helpers;
        if (name === '@/services/admin-seo.service') return { adminSeoService: service };
        if (name === '@/utils/response') return { handleSuccessResponse: (response) => notices.push(response) };
        if (name === '@/utils/editor-image-upload') return { uploadEditorImages: async (content) => content };
        if (name === 'vue-router') return { useRoute: () => route, useRouter: () => ({ replace: async (url) => replacements.push(url) }), RouterLink: panel };
        if (name === '@headlessui/vue') return { Dialog: { props: ['open'], setup: (props, { slots }) => () => props.open ? h('div', { role: 'dialog' }, slots.default?.()) : null }, DialogPanel: panel, DialogTitle: panel, DialogDescription: panel };
        if (name.startsWith('@/components/')) return { default: panel };
        return require(name);
    }, module);
    let state;
    return {
        calls, replacements, notices,
        async actions() { module.default.setup({}, { expose: (value) => { state = value; } }); await state.load(); return state; },
        async render() {
            const component = module.default;
            const setup = component.setup;
            component.setup = async (props, context) => { const render = setup(props, { ...context, expose: (value) => { state = value; } }); await state.load(); return render; };
            return renderToString(createSSRApp(component));
        },
    };
}

test('snapshot mapping preserves null false arrays and clears fields missing in older data', () => {
    const snapshot = { seo_title: null, seo_description: null, cover_image: null, cover_alt: null, canonical_url: null, meta_keywords: null, focus_keyword: null, excerpt: null, faq: null, article_schema: false, breadcrumb_schema: false };
    const mapped = helpers.seoPostFormFromSnapshot(snapshot);
    for (const [key, value] of Object.entries(snapshot)) assert.equal(mapped[key], value);
    const legacy = helpers.seoPostFormFromSnapshot({ title: 'Legacy', content: [{ type: 'paragraph', children: [{ text: 'Old' }] }] });
    assert.equal(legacy.article_schema, false);
    assert.equal(legacy.breadcrumb_schema, false);
    assert.equal(legacy.seo_title, 'Legacy');
    assert.equal(legacy.meta_keywords, null);
    assert.equal(legacy.language, 'vi');
    assert.equal(legacy.seo_category_id, null);
    const clone = helpers.seoPostFormFromSnapshot(legacy);
    clone.content[0].children[0].text = 'Changed';
    assert.equal(legacy.content[0].children[0].text, 'Old');
});

test('editor layout orders SEO fields and puts Google preview and save above the cover', async () => {
    const html = await environment().render();
    assert.ok(html.indexOf('Bản SEO cũ') < html.indexOf('Soạn bài SEO'));
    assert.ok(html.includes('xl:grid-cols-[minmax(0,1fr)_360px]'));
    const labels = ['Thông tin bài viết', 'SEO title', 'SEO description', 'Keyword', 'Đường dẫn bài viết (slug)', 'Danh mục', 'Ngôn ngữ bài viết', 'Nội dung bài viết'];
    let last = html.indexOf('Thông tin bài viết');
    for (const label of labels.slice(1)) { const next = html.indexOf(label, last); assert.ok(next > last, label); last = next; }
    const google = html.indexOf('Hiển thị trên Google');
    const save = html.indexOf('Lưu bài viết', google);
    assert.ok(save > google && save < html.indexOf('Ảnh đại diện SEO', save));
    assert.ok(html.includes('SEO mới nhất'));
    assert.ok(html.includes('https://example.com/kien-thuc/bai-moi'));
    assert.ok(!html.includes('Hẹn giờ'));
    assert.match(html, /<article[^>]*aria-labelledby="seo-content-title"[^>]*>[\s\S]*?<\/article>\s*<article[^>]*aria-labelledby="seo-faq-title"/);
    assert.ok(html.includes('Thêm câu hỏi'));
});

test('taking historical data only fills the form and saving updates the same current article', async () => {
    const env = environment();
    const state = await env.actions();
    state.form.focus_keyword = 'Newest keyword';
    await state.restoreRevision(3);
    assert.deepEqual(env.calls.map((call) => call[0]), ['revision']);
    assert.equal(state.form.seo_title, null);
    assert.equal(state.form.focus_keyword, null);
    assert.equal(state.form.article_schema, false);
    assert.equal(state.currentVersion.value, 7);
    assert.ok(state.notice.value.includes('cần bấm Lưu bài viết'));
    state.form.seo_title = 'Restored and edited';
    state.form.seo_category_id = 2;
    await state.handleSave();
    assert.equal(env.calls[1][0], 'update');
    assert.equal(env.calls[1][1], 5);
    assert.equal(env.calls[1][2].seo_title, 'Restored and edited');
    assert.equal(env.calls[1][2].expected_version, 7);
    assert.equal(env.calls[1][2].slug, 'ban-cu');
    assert.equal(env.replacements.length, 0);
});

test('first save publishes and redirects to editing the newly created article', async () => {
    const env = environment(null);
    const state = await env.actions();
    state.form.slug = 'new-post';
    state.form.seo_category_id = 2;
    state.form.status = 'draft';
    state.form.scheduled_at = '2030-01-01';
    const flushed = [{ type: 'heading', level: 1, children: [{ text: 'Fresh editor content' }] }];
    state.contentEditor.value = { flush: () => flushed, isUploadingImage: false };
    await state.handleSave();
    assert.equal(env.calls[0][0], 'create');
    assert.equal(env.calls[0][1].status, 'published');
    assert.equal(env.calls[0][1].scheduled_at, null);
    assert.deepEqual(env.calls[0][1].content, flushed);
    assert.deepEqual(env.replacements, ['/admin/seo/posts/32/edit']);
});

test('saving and restoration are blocked while cover or editor images upload', async () => {
    const env = environment();
    const state = await env.actions();
    state.coverUploader.value = { loading: true };
    await state.handleSave();
    await state.restoreRevision(2);
    assert.equal(env.calls.length, 0);
    state.coverUploader.value = { loading: false };
    state.contentEditor.value = { isUploadingImage: true };
    await state.handleSave();
    await state.restoreRevision(2);
    assert.equal(env.calls.length, 0);
});

test('empty history and failed revision loads leave the current form intact', async () => {
    const env = environment(5, { revision: async () => { throw new Error('History unavailable'); } });
    const state = await env.actions();
    await state.loadHistory();
    assert.equal(state.historyOpen.value, true);
    assert.equal(state.historyLoading.value, false);
    assert.equal(state.revisions.value.length, 0);
    const before = structuredClone(vue.toRaw(state.form));
    await state.restoreRevision(4);
    assert.deepEqual(vue.toRaw(state.form), before);
    assert.equal(state.historyError.value, 'History unavailable');
});

test('a stale save failure keeps unsaved restored data available for review', async () => {
    const env = environment(5, { updatePost: async () => { throw { response: { data: { message: 'Bài viết đã được cập nhật ở phiên khác.' } } }; } });
    const state = await env.actions();
    state.form.seo_title = 'Unsaved changes';
    await state.handleSave();
    assert.equal(state.form.seo_title, 'Unsaved changes');
    assert.equal(state.saving.value, false);
    assert.ok(state.errorMessage.value.includes('phiên khác'));
    assert.equal(env.replacements.length, 0);
});
