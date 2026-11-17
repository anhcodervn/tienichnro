export function initializeSeoArticles(documentRoot = document) {
    documentRoot.querySelectorAll('[data-seo-article]').forEach((root) => initializeSeoArticle(root, documentRoot));
}

export function initializeSeoArticle(root, documentRoot = document) {
    const content = root.querySelector('[data-seo-content]');
    const expand = root.querySelector('[data-seo-expand]');
    const label = root.querySelector('[data-seo-expand-label]');
    const fade = root.querySelector('[data-seo-fade]');
    const toggle = root.querySelector('[data-seo-toc-toggle]');
    const toc = root.querySelector('[data-seo-toc]');
    const list = root.querySelector('[data-seo-toc-list]');
    let expanded = false;
    const closeToc = () => {
        toc.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Mở mục lục');
    };
    const refresh = () => {
        if (!expand) return;
        const overflowing = content.scrollHeight > 256;
        const open = expanded || !overflowing;
        root.classList.toggle('seo-article--collapsed', !open);
        expand.hidden = !overflowing;
        expand.setAttribute('aria-expanded', String(open));
        label.textContent = open ? 'Thu gọn' : 'Xem thêm';
        expand.querySelector('i').classList.toggle('rotate-180', open);
        fade.hidden = open;
    };
    const setExpanded = (value) => {
        expanded = value;
        refresh();
    };
    expand?.addEventListener('click', () => {
        const wasExpanded = expanded;
        setExpanded(!expanded);
        closeToc();
        if (wasExpanded) root.scrollIntoView({ block: 'start' });
    });
    content.addEventListener('focusin', () => setExpanded(true));
    const headings = Array.from(content.querySelectorAll('h2, h3, h4')).filter((heading) => heading.textContent.trim());
    headings.forEach((heading, index) => {
        const base = heading.id || root.id + '-section-' + (index + 1);
        let id = base;
        let suffix = 2;
        while (documentRoot.getElementById(id) && documentRoot.getElementById(id) !== heading) id = base + '-' + suffix++;
        heading.id = id;
        const item = documentRoot.createElement('li');
        const link = documentRoot.createElement('a');
        link.href = '#' + encodeURIComponent(id);
        link.className =
            'block rounded-[10px] px-2 py-1 leading-6 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600';
        if (heading.tagName !== 'H2') link.classList.add('ml-3');
        link.textContent = heading.textContent.trim();
        link.addEventListener('click', () => {
            setExpanded(true);
            closeToc();
            heading.setAttribute('tabindex', '-1');
            heading.focus({ preventScroll: true });
        });
        item.appendChild(link);
        list.appendChild(item);
    });
    toggle.hidden = headings.length === 0;
    toggle.addEventListener('click', () => {
        toc.hidden = !toc.hidden;
        toggle.setAttribute('aria-expanded', String(!toc.hidden));
        toggle.setAttribute('aria-label', toc.hidden ? 'Mở mục lục' : 'Đóng mục lục');
        if (!toc.hidden) list.querySelector('a')?.focus();
    });
    root.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !toc.hidden) {
            event.preventDefault();
            closeToc();
            toggle.focus();
        }
    });
    documentRoot.addEventListener('click', (event) => {
        if (!root.contains(event.target)) closeToc();
    });
    refresh();
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(refresh).observe(content);
    content.querySelectorAll('img').forEach((image) => image.addEventListener('load', refresh));
}
