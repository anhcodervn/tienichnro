import api from '@/config/axios';

export type GlobalTopupPackage = {
    id: number;
    provider_id: number | null;
    provider_name: string | null;
    provider_slug: string | null;
    name: string;
    code: string;
    denomination: number;
    provider_price: number;
    price: number;
    original_price: number;
    discount_percent: number;
    description: string | null;
    bonus_text: string | null;
    status: 'active' | 'inactive';
    sort_order: number;
    metadata: Record<string, unknown>;
    packages_count: number;
};

export type GlobalPackageProvider = { id: number; name: string; slug: string };

export type GlobalPackageCatalog = {
    global_packages: GlobalTopupPackage[];
    providers: GlobalPackageProvider[];
};

const root = '/api/admin-api/global-topup-packages';

export const adminGlobalPackageService = {
    async catalog(): Promise<GlobalPackageCatalog> {
        const response = await api.get(root);
        return response.data.data;
    },
    save: (id: number | null, payload: Record<string, unknown>) => (id ? api.put(`${root}/${id}`, payload) : api.post(root, payload)),
    delete: (id: number) => api.delete(`${root}/${id}`),
};
