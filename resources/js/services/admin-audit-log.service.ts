import api from '@/config/axios';

export type AdminAuditActor = {
    id: number;
    name: string | null;
    full_name: string | null;
    email: string | null;
    username: string | null;
};

export type AdminAuditLog = {
    id: number;
    admin_id: number | null;
    request_id: string | null;
    action: string;
    route_name: string | null;
    method: string | null;
    path: string | null;
    status_code: number | null;
    duration_ms: number | null;
    subject_type: string;
    subject_id: number;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip: string | null;
    user_agent: string | null;
    created_at: string;
    admin: AdminAuditActor | null;
};

export type AdminAuditLogParams = {
    search?: string;
    admin_id?: number | string;
    action?: string;
    method?: string;
    status_code?: number | string;
    date_from?: string;
    date_to?: string;
    page?: number;
    per_page?: number;
};

export type AdminAuditLogResponse = {
    logs: {
        data: AdminAuditLog[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    filter_options: {
        admins: AdminAuditActor[];
        actions: string[];
    };
};

export const adminAuditLogService = {
    async list(params: AdminAuditLogParams = {}): Promise<AdminAuditLogResponse> {
        const response = await api.get('/api/admin-api/audit-logs', { params });

        return response.data.data as AdminAuditLogResponse;
    },
};
