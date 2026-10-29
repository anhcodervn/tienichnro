import type { SeoFaqItem, SeoRobotsValue } from './admin-seo.type';

export interface ToolSeoPayload {
    title: string;
    excerpt: string | null;
    language: string;
    content: unknown[];
    faq: SeoFaqItem[];
    seo_title: string | null;
    seo_description: string | null;
    meta_keywords: string | null;
    focus_keyword: string | null;
    cover_image: string | null;
    cover_alt: string | null;
    robots: SeoRobotsValue;
    article_schema: boolean;
    breadcrumb_schema: boolean;
    is_published: boolean;
    expected_version: number;
}

export interface ToolSeoSettings extends Omit<ToolSeoPayload, 'expected_version'> {
    lock_version: number;
    updated_at: string | null;
    slug: string;
    path: string | null;
    canonical_url: string | null;
    is_local: boolean;
}
