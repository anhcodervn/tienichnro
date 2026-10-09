import api from '@/config/axios';

export type AdminUserStatus = 'active' | 'blocked' | 'inactive';

export type AdminUserListItem = {
    id: number;
    name: string | null;
    username: string | null;
    email: string | null;
    phone: string | null;
    role: 'user' | 'admin';
    status: AdminUserStatus;
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
    };
};

export type AdminUserDetailResponse = {
    id: number;
    name: string | null;
    username: string | null;
    email: string | null;
    phone: string | null;
    role: 'user' | 'admin';
    status: AdminUserStatus;
    avatar: string | null;
    created_at: string | null;
    updated_at: string | null;
    last_login_at: string | null;
    last_login_ip: string | null;
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

export type AdminUserLog = {
    id: number;
    action: string;
    description: string | null;
    ip: string | null;
    user_agent: string | null;
    created_at: string | null;
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

export const adminUserService = {
    async list(params: AdminUserListParams = {}): Promise<AdminUserListResponse> {
        const response = await api.get('/api/admin-api/users', { params });

        return response.data.data;
    },

    async show(userId: number | string): Promise<AdminUserDetailResponse> {
        const response = await api.get(`/api/admin-api/users/${userId}`);

        return response.data.data;
    },

    async logs(userId: number | string, params: Record<string, unknown> = {}): Promise<PaginatedAdminUserRelation<AdminUserLog>> {
        const response = await api.get(`/api/admin-api/users/${userId}/logs`, { params });

        return response.data.data;
    },

    async updateStatus(userId: number | string, status: 'active' | 'blocked'): Promise<void> {
        await api.patch(`/api/admin-api/users/${userId}/status`, { status });
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
};
