import api from '@/config/axios';

export type AdminUserStatus = 'active' | 'blocked' | 'inactive';

export type AdminUserListItem = {
    id: number;
    name: string | null;
    username: string | null;
    email: string | null;
    phone: string | null;
    role: 'user' | 'admin' | 'ctv';
    status: AdminUserStatus;
    wallet_balance: number | null;
    created_at: string | null;
    last_login_at: string | null;
};

export type AdminUserListResponse = {
    data: AdminUserListItem[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    stats: {
        total_users: number;
        new_today: number;
        active_users: number;
        blocked_users: number;
        total_user_wallet_balance: number;
    };
};

export type AdminUserDetailResponse = {
    id: number;
    name: string | null;
    username: string | null;
    email: string | null;
    phone: string | null;
    role: 'user' | 'admin' | 'ctv';
    has_game_service_secondary_password: boolean;
    status: AdminUserStatus;
    avatar: string | null;
    created_at: string | null;
    updated_at: string | null;
    last_login_at: string | null;
    last_login_ip: string | null;
    wallet: {
        id: number;
        balance: number;
        hold_balance: number;
        total_spent: number;
    } | null;
    stats: {
        total_spent: number;
        order_count: number;
        completed_order_count: number;
    };
    latest_login: {
        at: string | null;
        ip: string | null;
    };
};

export type AdminPaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export type AdminUserWalletTransaction = {
    id: number;
    code: string;
    user: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
    } | null;
    type: string;
    amount: number;
    balance_before: number;
    balance_after: number;
    content: string | null;
    status: string;
    created_at: string | null;
};

export type AdminUserLog = {
    id: number;
    action: string;
    description: string | null;
    ip: string | null;
    user_agent: string | null;
    created_at: string | null;
};

export type AdminUserPricingMode = 'discount' | 'fixed' | 'profit';

export type AdminUserPackagePrice = {
    package_id: number;
    game_id: number;
    game: string | null;
    package: string;
    denomination: number;
    cost_price: number | null;
    base_price: number;
    base_profit: number | null;
    member_price: number;
    member_profit: number | null;
    discount_amount: number;
    pricing_mode: AdminUserPricingMode;
    discount_percent: number;
    fixed_price: number | null;
    minimum_profit: number;
    is_active: boolean;
    pricing_source: 'standard' | 'global' | 'package';
};

export type AdminUserGlobalPackagePreview = {
    id: number;
    name: string;
    denomination: number;
    cost_price: number | null;
    base_price: number;
    base_profit: number | null;
    member_price: number;
    member_profit: number | null;
    discount_amount: number;
    pricing_mode: AdminUserPricingMode;
    discount_percent: number;
    fixed_price: number | null;
    minimum_profit: number;
    is_active: boolean;
};

export type AdminUserPricingResponse = {
    prices: AdminUserPackagePrice[];
    global_packages: AdminUserGlobalPackagePreview[];
};

export type AdminUserGameServicePermission = {
    id: number;
    game_id: number;
    game_name: string;
    name: string;
    slug: string;
    code: string;
    status: 'active' | 'inactive';
    is_allowed: boolean;
};

export type AdminUserGameServicePermissionResponse = {
    services: AdminUserGameServicePermission[];
    selected_ids: number[];
};

export type QuickSetUserPricesPayload = {
    scope: 'packages' | 'global';
    package_ids: number[];
    pricing_mode: 'discount' | 'profit';
    discount_percent: number | null;
    profit_amount: number | null;
    is_active: boolean;
};

export type PaginatedAdminUserRelation<T> = {
    data: T[];
    meta: AdminPaginationMeta;
};

export type AdminUserListParams = {
    search?: string;
    status?: string;
    role?: string;
    date_from?: string;
    date_to?: string;
    per_page?: number;
    page?: number;
};

export type AdminUserDiscountItem = {
    id: number;
    name: string | null;
    username: string | null;
    email: string | null;
    phone: string | null;
    status: AdminUserStatus;
    package_rules_count: number;
    active_package_rules_count: number;
    global_rules_count: number;
    active_global_rules_count: number;
    pricing_modes: AdminUserPricingMode[];
    discount_rates: number[];
    updated_at: string | null;
};

export type AdminUserDiscountListResponse = {
    data: AdminUserDiscountItem[];
    meta: AdminPaginationMeta;
    stats: {
        discounted_users: number;
        active_users: number;
        package_rules: number;
        global_rules: number;
    };
};

export type AdminUserDiscountListParams = {
    search?: string;
    rule_status?: 'active' | 'inactive';
    scope?: 'packages' | 'global';
    per_page?: number;
    page?: number;
};

export const adminUserService = {
    async list(params: AdminUserListParams = {}): Promise<AdminUserListResponse> {
        const response = await api.get('/api/admin-api/users', { params });

        return response.data.data;
    },

    async show(userId: number | string): Promise<AdminUserDetailResponse> {
        const response = await api.get(`/api/admin-api/users/${userId}`);

        return response.data.data;
    },

    async discounts(params: AdminUserDiscountListParams = {}): Promise<AdminUserDiscountListResponse> {
        const response = await api.get('/api/admin-api/users/discounts', { params });

        return response.data.data as AdminUserDiscountListResponse;
    },

    async bulkSetDiscounts(payload: {
        user_ids: number[];
        scope: 'packages' | 'global' | 'all';
        discount_percent: number;
    }): Promise<{ users_updated: number; package_rules_upserted: number; global_rules_upserted: number }> {
        const response = await api.put('/api/admin-api/users/discounts/bulk', payload);

        return response.data.data;
    },

    async prices(userId: number | string): Promise<AdminUserPricingResponse> {
        const response = await api.get(`/api/admin-api/users/${userId}/prices`);

        return response.data.data as AdminUserPricingResponse;
    },

    async gameServices(userId: number | string): Promise<AdminUserGameServicePermissionResponse> {
        const response = await api.get(`/api/admin-api/users/${userId}/game-services`);

        return response.data.data as AdminUserGameServicePermissionResponse;
    },

    async syncGameServices(userId: number | string, gameServiceIds: number[]): Promise<AdminUserGameServicePermissionResponse> {
        const response = await api.put(`/api/admin-api/users/${userId}/game-services`, {
            game_service_ids: gameServiceIds,
        });

        return response.data.data as AdminUserGameServicePermissionResponse;
    },

    async updateGameServiceSecondaryPassword(
        userId: number | string,
        payload: { password: string; password_confirmation: string },
    ): Promise<{ has_game_service_secondary_password: boolean }> {
        const response = await api.put(`/api/admin-api/users/${userId}/game-service-secondary-password`, payload);

        return response.data.data;
    },

    async quickSetPrices(userId: number | string, payload: QuickSetUserPricesPayload): Promise<AdminUserPricingResponse> {
        const response = await api.put(`/api/admin-api/users/${userId}/prices/quick-set`, payload);

        return response.data.data as AdminUserPricingResponse;
    },

    async updatePrice(userId: number | string, packageId: number, payload: Record<string, unknown>): Promise<AdminUserPricingResponse> {
        const response = await api.put(`/api/admin-api/users/${userId}/prices/${packageId}`, payload);

        return response.data.data as AdminUserPricingResponse;
    },

    async deletePrice(userId: number | string, packageId: number): Promise<AdminUserPricingResponse> {
        const response = await api.delete(`/api/admin-api/users/${userId}/prices/${packageId}`);

        return response.data.data as AdminUserPricingResponse;
    },

    async updateGlobalPrice(userId: number | string, globalPackageId: number, payload: Record<string, unknown>): Promise<AdminUserPricingResponse> {
        const response = await api.put(`/api/admin-api/users/${userId}/global-prices/${globalPackageId}`, payload);

        return response.data.data as AdminUserPricingResponse;
    },

    async deleteGlobalPrice(userId: number | string, globalPackageId: number): Promise<AdminUserPricingResponse> {
        const response = await api.delete(`/api/admin-api/users/${userId}/global-prices/${globalPackageId}`);

        return response.data.data as AdminUserPricingResponse;
    },

    async walletTransactions(
        userId: number | string,
        params: Record<string, unknown> = {},
    ): Promise<PaginatedAdminUserRelation<AdminUserWalletTransaction>> {
        const response = await api.get(`/api/admin-api/users/${userId}/wallet-transactions`, { params });

        return response.data.data;
    },

    async logs(userId: number | string, params: Record<string, unknown> = {}): Promise<PaginatedAdminUserRelation<AdminUserLog>> {
        const response = await api.get(`/api/admin-api/users/${userId}/logs`, { params });

        return response.data.data;
    },

    async updateStatus(userId: number | string, status: 'active' | 'blocked'): Promise<void> {
        await api.patch(`/api/admin-api/users/${userId}/status`, { status });
    },

    async updateRole(userId: number | string, role: 'user' | 'ctv'): Promise<void> {
        await api.patch(`/api/admin-api/users/${userId}/role`, { role });
    },

    async resetPassword(
        userId: number | string,
        payload: {
            password: string;
            password_confirmation: string;
        },
    ): Promise<void> {
        await api.post(`/api/admin-api/users/${userId}/reset-password`, payload);
    },

    async adjustWallet(
        userId: number | string,
        payload: {
            type: 'add' | 'subtract';
            amount: number;
            note?: string;
        },
    ): Promise<{
        wallet: {
            id: number;
            balance: number;
            hold_balance: number;
            total_spent: number;
        };
        transaction: {
            id: number;
            type: string;
            amount: number;
            balance_before: number;
            balance_after: number;
            description: string | null;
            status: string;
            created_at: string | null;
        };
    }> {
        const response = await api.post(`/api/admin-api/users/${userId}/wallet-adjust`, payload);

        return response.data.data;
    },
};
