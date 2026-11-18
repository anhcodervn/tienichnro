import api from '@/config/axios';

export type ServerType = 'carot' | 'website' | 'game';
export type ManagedServer = { id: number; type: ServerType; server_id: string; name: string; is_active: boolean; sort_order: number };
export type ServerDraft = Pick<ManagedServer, 'server_id' | 'name' | 'is_active' | 'sort_order'>;
export const serverTitles: Record<ServerType, string> = { carot: 'Server nạp Carot', website: 'Server login website', game: 'Server login game' };
export const managedServerService = {
    async list(
        type: ServerType,
        params: { q: string; active: string; page: number },
    ): Promise<{ data: ManagedServer[]; current_page: number; last_page: number; total: number }> {
        return (await api.get(`/api/admin-api/managed-servers/${type}`, { params })).data;
    },
    async save(type: ServerType, draft: ServerDraft, id: number | null): Promise<ManagedServer> {
        return (
            id === null
                ? await api.post(`/api/admin-api/managed-servers/${type}`, draft)
                : await api.put(`/api/admin-api/managed-servers/${type}/${id}`, draft)
        ).data.data;
    },
    async remove(type: ServerType, id: number): Promise<void> {
        await api.delete(`/api/admin-api/managed-servers/${type}/${id}`);
    },
    async configuration(): Promise<{ credentials_saved: boolean; endpoint: string }> {
        return (await api.get('/api/admin-api/managed-servers/napcarot/configuration')).data.data;
    },
    async configure(data: { api_key?: string; api_secret?: string }): Promise<{ credentials_saved: boolean; endpoint: string }> {
        return (await api.put('/api/admin-api/managed-servers/napcarot/configuration', data)).data.data;
    },
    async sync(): Promise<{ count: number }> {
        return (await api.post('/api/admin-api/managed-servers/napcarot/sync')).data.data;
    },
};
