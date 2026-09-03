import api from '@/config/axios';

export type AdminRechargeHistoryItem = {
    id: number;
    transaction_code: string;
    amount: number;
    bonus_amount: number;
    bonus_percent: number;
    credited_amount: number;
    content: string | null;
    status: 'pending' | 'processing' | 'paid' | 'failed' | 'cancelled' | 'expired';
    bank_name: string | null;
    account_number: string | null;
    account_name: string | null;
    confirmed_at: string | null;
    expires_at: string | null;
    created_at: string | null;
    user: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
    } | null;
};

export type AdminRechargeHistoryResponse = {
    data: AdminRechargeHistoryItem[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    stats: {
        total_amount: number;
        today_count: number;
        pending_count: number;
        matched_count: number;
        success_count: number;
        failed_count: number;
    };
};

export type AdminRechargeHistoryParams = {
    search?: string;
    user_id?: number | string;
    status?: string;
    bank_code?: string;
    date_from?: string;
    date_to?: string;
    per_page?: number;
    page?: number;
};

export const adminRechargeHistoryService = {
    async list(params: AdminRechargeHistoryParams = {}): Promise<AdminRechargeHistoryResponse> {
        const response = await api.get('/api/admin-api/recharge-history', { params });

        return response.data.data as AdminRechargeHistoryResponse;
    },
};
