import api from '@/config/axios';

export type VerificationConfiguration = { price: number | null };
export type VerificationOrder = {
    id: number;
    attempt_version: number;
    can_retry: boolean;
    has_saved_credentials: boolean;
    queued_at: string | null;
    username: string;
    server_code: number;
    member: string;
    status: string;
    status_label: string;
    progress: string;
    error_code: string | null;
    error_message: string | null;
    charged_amount: number;
    created_at: string;
    completed_at: string | null;
};
export const gameVerificationStatuses = {
    pending: 'Chờ xử lý',
    processing: 'Đang lấy cú pháp',
    success: 'Đã lấy cú pháp',
    error: 'Lỗi',
};
export const adminGameVerificationService = {
    async retry(id: number, data: { attempt_version: number }): Promise<void> {
        await api.post(`/api/admin-api/game-verification/orders/${id}/retry`, data);
    },
    async clearSelected(ids: number[]): Promise<{ deleted: number; skipped: number }> {
        return (await api.delete('/api/admin-api/game-verification/history', { data: { ids } })).data.data;
    },
    async clearHistory(olderThanDays: number): Promise<{ deleted: number; has_more: boolean }> {
        return (await api.delete('/api/admin-api/game-verification/history', { data: { older_than_days: olderThanDays } })).data.data;
    },
    async configuration(): Promise<VerificationConfiguration> {
        return (await api.get('/api/admin-api/game-verification/configuration')).data.data;
    },
    async configure(price: number): Promise<VerificationConfiguration> {
        return (await api.put('/api/admin-api/game-verification/configuration', { price })).data.data;
    },
    async history(params: {
        page: number;
        q: string;
        status: string;
    }): Promise<{ data: VerificationOrder[]; meta: { current_page: number; last_page: number; total: number } }> {
        return (await api.get('/api/admin-api/game-verification/history', { params })).data;
    },
};
