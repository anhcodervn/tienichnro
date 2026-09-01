import api from '@/config/axios';

export type GlobalPackageLevelPrice = {
    id: number;
    member_level_id: number;
    global_topup_package_id: number;
    pricing_mode: 'discount' | 'fixed';
    discount_basis_points: number | null;
    fixed_price: number | null;
    minimum_profit: number | null;
    is_active: boolean;
};

export type GlobalTopupPackage = {
    id: number;
    name: string;
    code: string;
    denomination: number;
    price: number;
    original_price: number;
    discount_percent: number;
    description: string | null;
    status: 'active' | 'inactive';
    sort_order: number;
    packages_count: number;
    provider_price_min: number | null;
    provider_price_max: number | null;
    level_prices: GlobalPackageLevelPrice[];
};

export type GlobalPackageLevel = {
    id: number;
    name: string;
    rank: number;
    default_discount_bps: number;
    minimum_profit: number;
    status: 'active' | 'inactive';
};

export type GlobalPackageCatalog = { global_packages: GlobalTopupPackage[]; levels: GlobalPackageLevel[] };

const root = '/api/admin-api/global-topup-packages';

export const adminGlobalPackageService = {
    async catalog(): Promise<GlobalPackageCatalog> {
        const response = await api.get(root);
        return response.data.data;
    },
    save: (id: number | null, payload: Record<string, unknown>) => (id ? api.put(`${root}/${id}`, payload) : api.post(root, payload)),
    delete: (id: number) => api.delete(`${root}/${id}`),
    saveLevelPrice: (packageId: number, levelId: number, payload: Record<string, unknown>) =>
        api.put(`${root}/${packageId}/levels/${levelId}`, payload),
    deleteLevelPrice: (packageId: number, levelId: number) => api.delete(`${root}/${packageId}/levels/${levelId}`),
};
