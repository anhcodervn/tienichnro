import api from '@/config/axios';

export type RechargeBank = { bank_id: number; bank_name: string; account_number: string; account_name: string };
export type RechargeCheck = { checked: number; credited: number; errors: number; at: string };
export type RechargeConfiguration = {
    enabled: boolean;
    bank_id: number;
    bank: Partial<RechargeBank>;
    credentials_saved: boolean;
    cron_key_saved: boolean;
    cron_url: string;
    last_check: RechargeCheck | null;
};
export type RechargeRow = {
    id: number;
    code: string;
    provider_code: string | null;
    amount: number;
    status: string;
    bank_name: string;
    account_number: string;
    last_error: string | null;
    created_at: string;
    paid_at: string | null;
    user: { username: string } | null;
};
export const rechargeStatuses: Record<string, string> = {
    creating: 'Đang tạo lệnh',
    pending: 'Chờ chuyển khoản',
    processing: 'Đang kiểm tra',
    paid: 'Đã cộng tiền',
    expired: 'Hết hạn',
    failed: 'Không thành công',
    cancelled: 'Đã huỷ',
    creation_failed: 'Chưa tạo được lệnh',
};
export const adminRechargeService = {
    async configuration(): Promise<RechargeConfiguration> {
        return (await api.get('/api/admin-api/recharge/configuration')).data.data;
    },
    async save(data: {
        enabled: boolean;
        bank_id: number | null;
        api_key?: string;
        api_secret?: string;
        cron_key?: string;
    }): Promise<RechargeConfiguration> {
        return (await api.put('/api/admin-api/recharge/configuration', data)).data.data;
    },
    async banks(data: { api_key?: string; api_secret?: string }): Promise<RechargeBank[]> {
        return (await api.post('/api/admin-api/recharge/banks', data)).data.data;
    },
    async check(): Promise<RechargeCheck> {
        return (await api.post('/api/admin-api/recharge/check')).data.data;
    },
    async history(params: {
        q: string;
        status: string;
        page: number;
    }): Promise<{ data: RechargeRow[]; current_page: number; last_page: number; total: number }> {
        return (await api.get('/api/admin-api/recharge/history', { params })).data;
    },
};
