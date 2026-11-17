import api from '@/config/axios';

export type PasswordConfiguration = { price: number | null };
export type PasswordOrder = {
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
    refunded_at: string | null;
    created_at: string;
    completed_at: string | null;
};
export const gamePasswordStatuses = {
    pending: 'Chờ xử lý',
    processing: 'Đang đổi mật khẩu',
    success: 'Hoàn tất',
    error: 'Lỗi',
    needs_review: 'Cần kiểm tra',
};
export const adminGamePasswordService = {
    async retry(id: number, data: { attempt_version: number }): Promise<void> {
        await api.post(`/api/admin-api/game-password/orders/${id}/retry`, data);
    },
    async clearSelected(ids: number[]): Promise<{ deleted: number; skipped: number }> {
        return (await api.delete('/api/admin-api/game-password/history', { data: { ids } })).data.data;
    },
    async clearHistory(olderThanDays: number): Promise<{ deleted: number; has_more: boolean }> {
        return (await api.delete('/api/admin-api/game-password/history', { data: { older_than_days: olderThanDays } })).data.data;
    },
    async configuration(): Promise<PasswordConfiguration> {
        return (await api.get('/api/admin-api/game-password/configuration')).data.data;
    },
    async configure(data: { price: number }): Promise<PasswordConfiguration> {
        return (await api.put('/api/admin-api/game-password/configuration', data)).data.data;
    },
    async history(params: {
        page: number;
        q: string;
        status: string;
    }): Promise<{ data: PasswordOrder[]; meta: { current_page: number; last_page: number; total: number } }> {
        return (await api.get('/api/admin-api/game-password/history', { params })).data;
    },
    async resolve(id: number, status: 'success' | 'error'): Promise<void> {
        await api.patch(`/api/admin-api/game-password/orders/${id}/resolve`, { status });
    },
};
