import api from '@/config/axios';

export type GameImageStatus = 'pending' | 'processing' | 'success' | 'error' | 'paused' | 'cancelled';
export const gameImageStatuses: Record<GameImageStatus, string> = {
    pending: 'Chờ ghép',
    processing: 'Đang chụp ảnh',
    success: 'Hoàn tất',
    error: 'Lỗi',
    paused: 'Tạm dừng',
    cancelled: 'Đã huỷ',
};
export type GameImageHistoryRow = {
    id: number;
    user_id: number;
    member: string;
    username: string;
    server: string;
    status: GameImageStatus;
    capture_version: number;
    error_code: string | null;
    error_message: string | null;
    charged_amount: number;
    refunded_at: string | null;
    refunded_amount?: number | null;
    created_at: string;
    processing_at: string | null;
    completed_at: string | null;
};
export const adminGameImageService = {
    async configuration(): Promise<{ price: number | null }> {
        return (await api.get('/api/admin-api/game-image/configuration')).data.data;
    },
    async configure(price: number): Promise<{ price: number }> {
        return (await api.put('/api/admin-api/game-image/configuration', { price })).data.data;
    },
    async history(params: { page: number; q: string; status: string }): Promise<{
        data: GameImageHistoryRow[];
        meta: { current_page: number; last_page: number; total: number };
        servers?: { code: string; name: string }[];
    }> {
        return (await api.get('/api/admin-api/game-image/history', { params })).data;
    },
    async update(id: number, data: { username: string; server: string; password?: string; capture_version: number }): Promise<void> {
        await api.patch(`/api/admin-api/game-image/accounts/${id}`, data);
    },
    async remove(id: number, capture_version: number): Promise<void> {
        await api.delete(`/api/admin-api/game-image/accounts/${id}`, { data: { capture_version } });
    },
};
