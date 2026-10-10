import api from '@/config/axios';

export type ZaloReceiver = {
    id: number;
    box_zalo_id: string | null;
    zalo_id: string;
    char_name: string;
    char_server: number | null;
    type_receive: string;
    created_at: string;
    updated_at: string;
};
export type ZaloReceiverDraft = Pick<ZaloReceiver, 'box_zalo_id' | 'zalo_id' | 'char_name' | 'char_server' | 'type_receive'>;
const endpoint = '/api/admin-api/nro/zalo-receivers';
export const adminZaloReceivers = {
    async list(): Promise<ZaloReceiver[]> {
        return (await api.get(endpoint)).data.data;
    },
    async save(draft: ZaloReceiverDraft, id: number | null): Promise<ZaloReceiver> {
        return (id === null ? await api.post(endpoint, draft) : await api.patch(`${endpoint}/${id}`, draft)).data.data;
    },
    async remove(id: number): Promise<void> {
        await api.delete(`${endpoint}/${id}`);
    },
};
