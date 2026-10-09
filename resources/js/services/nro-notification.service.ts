import api from '@/config/axios';
export type NroBoss = { id: number; code: string; name: string; game_names: string[] | null; respawn_seconds: number | null; is_active: boolean; sort_order: number };
export type NroBossPayload = Omit<NroBoss, 'id' | 'game_names'> & { game_names?: string | string[] };
export type NroServer = { id: number; server_code: number; code: string | null; name: string; is_active: boolean; sort_order: number };
export type NroServerPayload = Omit<NroServer, 'id'>;
export type NroNotificationGroup = { id: number; code: string; name: string };
export type NroNotificationType = {
    id: number;
    code: string;
    name: string;
    type_id: number;
    type: NroNotificationGroup;
    keywords: string[] | null;
    additional_filters: ('boss' | 'state')[] | null;
};
export type NroNotify = {
    id: number;
    server: NroServer;
    code: string;
    boss: NroBoss | null;
    is_boss: boolean;
    boss_global: boolean;
    boss_name: string | null;
    char_name: string | null;
    content: string;
    death_content: string | null;
    map_name: string | null;
    zone: number | null;
    zone_name: string | null;
    time_start: string;
    death_time: string | null;
    killed_by: string | null;
    respawn_at: string | null;
    state: 'notification' | 'living' | 'dead';
};
export type NroNotifyPage = { data: NroNotify[]; meta: { current_page: number; last_page: number; total: number } };
export const nroNotificationService = {
    async servers(): Promise<NroServer[]> {
        return (await api.get('/api/admin-api/nro/servers')).data.data;
    },
    async saveServer(payload: Partial<NroServerPayload>, id: number | null): Promise<NroServer> {
        const response =
            id === null ? await api.post('/api/admin-api/nro/servers', payload) : await api.patch(`/api/admin-api/nro/servers/${id}`, payload);
        return response.data.data;
    },
    async bosses(): Promise<NroBoss[]> {
        return (await api.get('/api/admin-api/nro/bosses')).data.data;
    },
    async options(): Promise<{ servers: NroServer[] }> {
        return (await api.get('/api/nro/options')).data.data;
    },
    async save(payload: NroBossPayload, id: number | null) {
        return id ? api.patch(`/api/admin-api/nro/bosses/${id}`, payload) : api.post('/api/admin-api/nro/bosses', payload);
    },
    async deleteBoss(id: number): Promise<void> {
        await api.delete(`/api/admin-api/nro/bosses/${id}`);
    },
    async notificationTypes(): Promise<{ codes: NroNotificationType[]; groups: NroNotificationGroup[] }> {
        return (await api.get('/api/admin-api/nro/notification-types')).data.data;
    },
    async createNotificationType(payload: {
        keywords?: string;
        code: string;
        name: string;
        type_id: number;
        additional_filters: ('boss' | 'state')[];
    }): Promise<NroNotificationType> {
        return (await api.post('/api/admin-api/nro/notification-types', payload)).data.data;
    },
    async updateNotificationType(
        id: number,
        payload: { code?: string; name: string; type_id: number; additional_filters?: ('boss' | 'state')[]; keywords?: string },
    ): Promise<NroNotificationType> {
        return (await api.patch(`/api/admin-api/nro/notification-types/${id}`, payload)).data.data;
    },
    async notifications(params: Record<string, string | number>): Promise<NroNotifyPage> {
        return (await api.get('/api/admin-api/nro/notifies', { params })).data;
    },
    async deleteNotificationType(id: number): Promise<void> {
        await api.delete(`/api/admin-api/nro/notification-types/${id}`);
    },
};
