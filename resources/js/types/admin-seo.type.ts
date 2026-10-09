export type SeoRobotsValue = 'index,follow' | 'noindex,follow';
export type SeoPostStatus = 'draft' | 'published' | 'scheduled';
export type SeoPageType = 'knowledge' | 'guide' | 'price';

export interface SeoFaqItem {
    question: string;
    answer: string;
}

export interface AdminHomeSeoSettings {
    meta_title: string;
    meta_description: string;
    meta_keywords: string;
    h1: string;
    article_title: string;
    content: unknown[];
    faqs: SeoFaqItem[];
    is_published: boolean;
}

export interface SeoServiceOption {
    id: number;
    name: string;
    slug: string;
    status: string;
}

export interface AdminSeoOverviewSummary {
    total_categories: number;
    indexed_categories: number;
    total_posts: number;
    published_posts: number;
    sitemap_files: number;
    technical_score: number;
}

export interface AdminSeoSitemapEntry {
    title: string;
    path: string;
    description: string;
    included_count: string;
}

export interface AdminSeoCategoryItem {
    id: number;
    name: string;
    slug: string;
    seo_title: string | null;
    seo_description: string | null;
    robots: SeoRobotsValue;
    is_active: boolean;
    sort_order: number;
    posts_count?: number;
    updated_at: string;
}

export interface AdminSeoPostItem {
    id: number;
    seo_category_id: number | null;
    type: SeoPageType;
    service_id: number | null;
    title: string;
    slug: string;
    excerpt: string | null;
    content: unknown[];
    faq: SeoFaqItem[] | null;
    cover_image: string | null;
    seo_title: string | null;
    seo_description: string | null;
    meta_keywords: string | null;
    canonical_url: string | null;
    robots: SeoRobotsValue;
    focus_keyword: string | null;
    cover_alt: string | null;
    article_schema: boolean;
    breadcrumb_schema: boolean;
    status: SeoPostStatus;
    published_at: string | null;
    scheduled_at: string | null;
    updated_at: string;
    category?: {
        id: number;
        name: string;
    } | null;
    service?: SeoServiceOption | null;
}

export interface AdminSeoCategoryPayload {
    name: string;
    slug: string;
    seo_title?: string;
    seo_description?: string;
    robots: SeoRobotsValue;
    is_active?: boolean;
    sort_order?: number;
}

export interface AdminSeoPostPayload {
    seo_category_id?: number | null;
    type: SeoPageType;
    service_id?: number | null;
    title: string;
    slug: string;
    excerpt?: string;
    content?: unknown[];
    faq?: SeoFaqItem[];
    cover_image?: string | null;
    seo_title?: string;
    seo_description?: string;
    meta_keywords?: string;
    canonical_url?: string | null;
    robots: SeoRobotsValue;
    focus_keyword?: string;
    cover_alt?: string;
    article_schema?: boolean;
    breadcrumb_schema?: boolean;
    status: SeoPostStatus;
    published_at?: string | null;
    scheduled_at?: string | null;
}
