import api from '@/config/axios';

export type MemberLevelPackagePrice = {
    id: number;
    member_level_id: number;
    topup_package_id: number;
    pricing_mode: 'discount' | 'fixed';
    discount_basis_points: number | null;
    fixed_price: number | null;
    minimum_profit: number | null;
    is_active: boolean;
};

export type MemberLevel = {
    id: number;
    code: string;
    name: string;
    rank: number;
    lifetime_threshold: number;
    maintenance_amount: number;
    maintenance_days: number;
    default_discount_bps: number;
    minimum_profit: number;
    color: string;
    icon: string;
    status: 'active' | 'inactive';
    sort_order: number;
    package_prices: MemberLevelPackagePrice[];
};

export type MemberLevelPackage = {
    id: number;
    game_id: number;
    name: string;
    price: number;
    provider_price: number | null;
    status: string;
};

export type MemberLevelCatalog = {
    levels: MemberLevel[];
    games: Array<{ id: number; name: string; packages: MemberLevelPackage[] }>;
};

export type MemberLevelPayload = Omit<MemberLevel, 'id' | 'package_prices'>;

export const adminMemberLevelService = {
    async catalog(): Promise<MemberLevelCatalog> {
        const response = await api.get('/api/admin-api/member-levels');
        return response.data.data;
    },
    async create(payload: MemberLevelPayload): Promise<void> {
        await api.post('/api/admin-api/member-levels', payload);
    },
    async update(levelId: number, payload: MemberLevelPayload): Promise<void> {
        await api.put(`/api/admin-api/member-levels/${levelId}`, payload);
    },
    async disable(levelId: number): Promise<void> {
        await api.delete(`/api/admin-api/member-levels/${levelId}`);
    },
    async savePackagePrice(levelId: number, packageId: number, payload: Record<string, unknown>): Promise<void> {
        await api.put(`/api/admin-api/member-levels/${levelId}/packages/${packageId}`, payload);
    },
    async deletePackagePrice(levelId: number, packageId: number): Promise<void> {
        await api.delete(`/api/admin-api/member-levels/${levelId}/packages/${packageId}`);
    },
    async assignUser(userId: number, payload: { member_level_id: number | null; expires_at: string | null; reason?: string }): Promise<unknown> {
        const response = await api.put(`/api/admin-api/member-levels/users/${userId}/assignment`, payload);
        return response.data.data.member_level;
    },
};
