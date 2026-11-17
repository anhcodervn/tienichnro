import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeSeoArticle } from '../../resources/js/client-seo-article.js';

function element(tagName = 'DIV') {
    const classes = new Set();
    return {
        tagName,
        id: '',
        hidden: true,
        textContent: '',
        attributes: {},
        children: [],
        handlers: {},
        classList: {
            add: (name) => classes.add(name),
            toggle: (name, on) => (on ? classes.add(name) : classes.delete(name)),
            contains: (name) => classes.has(name),
        },
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
        addEventListener(name, callback) {
            this.handlers[name] = callback;
        },
        appendChild(child) {
            this.children.push(child);
        },
        querySelector() {
            return this.children[0]?.children[0] || this.icon;
        },
        focus() {
            this.focused = true;
        },
        scrollIntoView() {
            this.scrolled = true;
        },
    };
}

function fixture(height = 600, titles = ['Cách sử dụng', 'Cách sử dụng'], collapsible = true) {
    const root = element();
    root.id = 'tool-seo';
    const selectors = ['content', 'expand', 'expand-label', 'fade', 'toc-toggle', 'toc', 'toc-list'];
    const nodes = Object.fromEntries(selectors.map((name) => [name, element()]));
    nodes.expand.icon = element('I');
    if (!collapsible) {
        delete nodes.expand;
        delete nodes['expand-label'];
        delete nodes.fade;
    }
    const headings = titles.map((text, index) => Object.assign(element(index ? 'H3' : 'H2'), { textContent: text, id: 'same-heading' }));
    const image = element('IMG');
    nodes.content.scrollHeight = height;
    nodes.content.querySelectorAll = (selector) => (selector === 'img' ? [image] : headings);
    root.querySelector = (selector) => nodes[selector.slice(10, -1)];
    root.contains = (target) => target !== 'outside';
    const doc = {
        handlers: {},
        getElementById: (id) => headings.find((heading) => heading.id === id),
        createElement: (tag) => element(tag.toUpperCase()),
        addEventListener(name, callback) {
            this.handlers[name] = callback;
        },
    };
    initializeSeoArticle(root, doc);
    return { root, nodes, headings, image, doc };
}

test('long SEO articles expand collapse and automatically reveal keyboard focused content', () => {
    const { root, nodes } = fixture();
    assert.equal(root.classList.contains('seo-article--collapsed'), true);
    assert.equal(nodes.expand.hidden, false);
    assert.equal(nodes.fade.hidden, false);
    assert.equal(nodes.expand.attributes['aria-expanded'], 'false');
    nodes.expand.handlers.click();
    assert.equal(root.classList.contains('seo-article--collapsed'), false);
    assert.equal(nodes['expand-label'].textContent, 'Thu gọn');
    nodes.expand.handlers.click();
    assert.equal(root.scrolled, true);
    assert.equal(nodes['expand-label'].textContent, 'Xem thêm');
    nodes.content.handlers.focusin();
    assert.equal(nodes.expand.attributes['aria-expanded'], 'true');
});

test('TOC deduplicates anchors reveals clipped sections and supports Escape and outside clicks', () => {
    const { root, nodes, headings, doc } = fixture();
    assert.equal(headings[1].id, 'same-heading-2');
    const links = nodes['toc-list'].children.map((item) => item.children[0]);
    assert.deepEqual(
        links.map((link) => link.href),
        ['#same-heading', '#same-heading-2'],
    );
    assert.equal(links[0].textContent, 'Cách sử dụng');
    nodes['toc-toggle'].handlers.click();
    assert.equal(nodes.toc.hidden, false);
    assert.equal(links[0].focused, true);
    links[1].handlers.click();
    assert.equal(root.classList.contains('seo-article--collapsed'), false);
    assert.equal(headings[1].focused, true);
    assert.equal(nodes.toc.hidden, true);
    nodes['toc-toggle'].handlers.click();
    root.handlers.keydown({ key: 'Escape', preventDefault() {} });
    assert.equal(nodes.toc.hidden, true);
    assert.equal(nodes['toc-toggle'].focused, true);
    nodes['toc-toggle'].handlers.click();
    doc.handlers.click({ target: 'outside' });
    assert.equal(nodes['toc-toggle'].attributes['aria-expanded'], 'false');
});

test('short articles omit unnecessary controls and remeasure when images load', () => {
    const { nodes, root, image } = fixture(100, []);
    assert.equal(nodes.expand.hidden, true);
    assert.equal(nodes['toc-toggle'].hidden, true);
    assert.equal(root.classList.contains('seo-article--collapsed'), false);
    nodes.content.scrollHeight = 900;
    image.handlers.load();
    assert.equal(nodes.expand.hidden, false);
    assert.equal(root.classList.contains('seo-article--collapsed'), true);
});

test('blog TOC opens navigates and closes without collapsing the full article', () => {
    const { root, nodes, headings, doc, image } = fixture(1800, ['Hướng dẫn', 'Chi tiết'], false);
    assert.equal(root.classList.contains('seo-article--collapsed'), false);
    assert.equal(nodes['toc-toggle'].hidden, false);
    nodes['toc-toggle'].handlers.click();
    assert.equal(nodes.toc.hidden, false);
    const link = nodes['toc-list'].children[1].children[0];
    link.handlers.click();
    assert.equal(headings[1].focused, true);
    assert.equal(nodes.toc.hidden, true);
    nodes.content.handlers.focusin();
    image.handlers.load();
    assert.equal(root.classList.contains('seo-article--collapsed'), false);
    nodes['toc-toggle'].handlers.click();
    doc.handlers.click({ target: 'outside' });
    assert.equal(nodes.toc.hidden, true);
});
