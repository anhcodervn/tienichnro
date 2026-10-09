import api from '@/config/axios';
import type {
    AdminHomeSeoSettings,
    AdminSeoCategoryItem,
    AdminSeoCategoryPayload,
    AdminSeoOverviewSummary,
    AdminSeoPostItem,
    AdminSeoPostPayload,
    AdminSeoSitemapEntry,
    SeoServiceOption,
} from '@/types/admin-seo.type';

export const adminSeoService = {
    async getHomeSeo(): Promise<AdminHomeSeoSettings> {
        const response = await api.get('/api/admin-api/seo/home');

        return response.data.data as AdminHomeSeoSettings;
    },

    async updateHomeSeo(payload: AdminHomeSeoSettings) {
        return api.patch('/api/admin-api/seo/home', payload);
    },

    async overview(): Promise<{ summary: AdminSeoOverviewSummary; sitemaps: AdminSeoSitemapEntry[] }> {
        const response = await api.get('/api/admin-api/seo/overview');

        return response.data.data as { summary: AdminSeoOverviewSummary; sitemaps: AdminSeoSitemapEntry[] };
    },

    async listCategories(params: Record<string, unknown> = {}): Promise<AdminSeoCategoryItem[]> {
        const response = await api.get('/api/admin-api/seo/categories', { params });

        return response.data.data.categories as AdminSeoCategoryItem[];
    },

    async createCategory(payload: AdminSeoCategoryPayload) {
        return api.post('/api/admin-api/seo/categories', payload);
    },

    async updateCategory(id: number | string, payload: Partial<AdminSeoCategoryPayload>) {
        return api.patch(`/api/admin-api/seo/categories/${id}`, payload);
    },

    async removeCategory(id: number | string) {
        return api.delete(`/api/admin-api/seo/categories/${id}`);
    },

    async listPosts(params: Record<string, unknown> = {}): Promise<{
        posts: AdminSeoPostItem[];
        categories: Array<{ id: number; name: string }>;
    }> {
        const response = await api.get('/api/admin-api/seo/posts', { params });

        return response.data.data as {
            posts: AdminSeoPostItem[];
            categories: Array<{ id: number; name: string }>;
        };
    },

    async getPost(id: number | string): Promise<AdminSeoPostItem> {
        const response = await api.get(`/api/admin-api/seo/posts/${id}`);

        return response.data.data as AdminSeoPostItem;
    },

    async postOptions(): Promise<{
        categories: Array<{ id: number; name: string; slug: string }>;
        services: SeoServiceOption[];
    }> {
        const response = await api.get('/api/admin-api/seo/post-options');

        return response.data.data as {
            categories: Array<{ id: number; name: string; slug: string }>;
            services: SeoServiceOption[];
        };
    },

    async createPost(payload: AdminSeoPostPayload) {
        return api.post('/api/admin-api/seo/posts', payload);
    },

    async updatePost(id: number | string, payload: Partial<AdminSeoPostPayload>) {
        return api.patch(`/api/admin-api/seo/posts/${id}`, payload);
    },

    async removePost(id: number | string) {
        return api.delete(`/api/admin-api/seo/posts/${id}`);
    },

    async sitemaps(): Promise<AdminSeoSitemapEntry[]> {
        const response = await api.get('/api/admin-api/seo/sitemaps');

        return response.data.data.entries as AdminSeoSitemapEntry[];
    },
};
