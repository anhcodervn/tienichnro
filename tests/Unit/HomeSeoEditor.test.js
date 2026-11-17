import { compileScript, parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import ts from 'typescript';
import * as vue from 'vue';

const require = createRequire(import.meta.url);
globalThis.window = { location: { origin: 'https://example.com' } };

function environment() {
    const saved = [];
    const initial = {
        meta_title: 'Tiêu đề Google',
        meta_description: 'Mô tả Google',
        meta_keywords: 'NRO',
        h1: 'Tiện ích NRO',
        article_title: 'Hướng dẫn',
        content: [],
        faqs: [{ question: 'Câu hỏi?', answer: 'Trả lời.' }],
        is_published: true,
    };
    const source = readFileSync(new URL('../../resources/js/pages/admin/seo/home/index.vue', import.meta.url), 'utf8').replace(
        '</script>',
        'defineExpose({form, loadSettings, save, contentEditor, addFaq, removeFaq});\n</script>',
    );
    const { descriptor } = parse(source);
    const script = compileScript(descriptor, { id: 'home-seo-test', inlineTemplate: true });
    const code = ts.transpileModule(script.content, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const exports = {};
    const panel = {
        setup:
            (_, { slots }) =>
            () =>
                vue.h('div', slots.default?.()),
    };
    new Function('require', 'exports', code)((name) => {
        if (name === 'vue') return { ...vue, onMounted() {} };
        if (name.startsWith('@/components/')) return { default: panel };
        if (name === '@/services/admin-seo.service')
            return {
                adminSeoService: {
                    getHomeSeo: async () => structuredClone(initial),
                    updateHomeSeo: async (payload) => {
                        saved.push(JSON.parse(JSON.stringify(payload)));
                        return { data: { data: payload } };
                    },
                },
            };
        if (name === '@/utils/editor-image-upload') return { uploadEditorImages: async (content) => content };
        if (name === '@/utils/response')
            return {
                handleErrorResponse(error) {
                    throw error;
                },
                handleSuccessResponse() {},
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
        await state.loadSettings();
        return render;
    };
    return { saved, state: () => state, render: () => renderToString(vue.createSSRApp(exports.default)) };
}

test('home SEO presents metadata content and FAQ beside a live Google preview and publication controls', async () => {
    const env = environment();
    const html = await env.render();
    const [main, sidebar] = html.split('<aside');
    assert.ok(main.includes('lg:grid-cols-[minmax(0,2.2fr)_minmax(16rem,1fr)]'));
    assert.ok(main.indexOf('data-seo-editor-context') < main.indexOf('Thông tin bài viết'));
    assert.ok(main.indexOf('Thông tin bài viết') < main.indexOf('Nội dung bài viết'));
    assert.ok(main.indexOf('Nội dung bài viết') < main.indexOf('Câu hỏi thường gặp'));
    assert.ok(sidebar.includes('Hiển thị trên Google'));
    assert.ok(sidebar.includes('Tiêu đề Google'));
    assert.ok(sidebar.includes('Mô tả Google'));
    assert.ok(sidebar.includes('https://example.com/'));
    assert.ok(sidebar.includes('Lưu SEO trang chủ'));
    assert.ok(main.includes('Tiêu đề trang chủ'));
    assert.ok(main.includes('Mô tả ngắn'));
    assert.ok(!main.includes('Tiêu đề H1 đầu trang'));
    assert.ok(!main.includes('Tiêu đề bài SEO'));
    assert.ok(!main.includes('value="Tiện ích NRO"'));
    assert.ok(!main.includes('value="Hướng dẫn"'));
    assert.ok(sidebar.includes('Hiển thị bài viết và FAQ'));
});

test('compact home editor still saves flushed content trimmed metadata and FAQ values', async () => {
    const env = environment();
    await env.render();
    const state = env.state();
    const content = [{ type: 'paragraph', children: [{ text: 'Nội dung mới' }] }];
    state.contentEditor.value = { flush: () => content };
    state.form.meta_title = '  Meta mới  ';
    state.form.faqs = [
        { question: ' Câu hỏi mới? ', answer: ' Câu trả lời mới. ' },
        { question: '', answer: '' },
    ];
    await state.save();
    assert.equal(env.saved.length, 1);
    assert.equal(env.saved[0].meta_title, 'Meta mới');
    assert.deepEqual(env.saved[0].content, content);
    assert.deepEqual(env.saved[0].faqs, [{ question: 'Câu hỏi mới?', answer: 'Câu trả lời mới.' }]);
    assert.equal(env.saved[0].is_published, true);
    assert.ok(!Object.hasOwn(env.saved[0], 'h1'));
    assert.ok(!Object.hasOwn(env.saved[0], 'article_title'));
});
