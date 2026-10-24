import type { AdminSeoPostPayload } from '@/types/admin-seo.type';

export function newSeoPostForm(): AdminSeoPostPayload {
    return {
        type: 'knowledge',
        service_id: null,
        language: 'vi',
        title: '',
        excerpt: null,
        slug: '',
        seo_category_id: null,
        content: [],
        faq: [],
        cover_image: null,
        cover_alt: null,
        seo_title: null,
        seo_description: null,
        meta_keywords: null,
        canonical_url: null,
        robots: 'index,follow',
        focus_keyword: null,
        article_schema: true,
        breadcrumb_schema: true,
        status: 'published',
        published_at: null,
        scheduled_at: null,
    };
}

export function seoPostFormFromSnapshot(snapshot: Partial<AdminSeoPostPayload>): AdminSeoPostPayload {
    const source = JSON.parse(JSON.stringify(snapshot)) as Partial<AdminSeoPostPayload>;
    return {
        type: source.type === 'guide' ? 'guide' : 'knowledge',
        service_id: null,
        language: source.language || 'vi',
        title: source.title ?? '',
        excerpt: source.excerpt ?? null,
        slug: source.slug ?? '',
        seo_category_id: source.seo_category_id ?? null,
        content: Array.isArray(source.content) ? source.content : [],
        faq: source.faq === null ? null : Array.isArray(source.faq) ? source.faq : [],
        cover_image: source.cover_image ?? null,
        cover_alt: source.cover_alt ?? null,
        seo_title: Object.hasOwn(source, 'seo_title') ? source.seo_title : (source.title ?? null),
        seo_description: Object.hasOwn(source, 'seo_description') ? source.seo_description : (source.excerpt ?? null),
        meta_keywords: source.meta_keywords ?? null,
        canonical_url: source.canonical_url ?? null,
        robots: source.robots === 'noindex,follow' ? 'noindex,follow' : 'index,follow',
        focus_keyword: source.focus_keyword ?? null,
        article_schema: source.article_schema === true,
        breadcrumb_schema: source.breadcrumb_schema === true,
        status: 'published',
        published_at: null,
        scheduled_at: null,
    };
}
