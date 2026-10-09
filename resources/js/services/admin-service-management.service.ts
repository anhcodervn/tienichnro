import api from '@/config/axios';

export type ManagedService = {
    code: string;
    name: string;
    sort_order: number;
    description: string;
    icon_type: 'icon' | 'image';
    icon: string;
    image_url: string | null;
    is_enabled: boolean;
    is_available: boolean;
    maintenance_message: string;
    url?: string | null;
    route?: string | null;
};

export const adminServiceManagement = {
    async list(): Promise<ManagedService[]> {
        return (await api.get('/api/admin-api/settings/services')).data.data;
    },
    async update(service: ManagedService): Promise<ManagedService> {
        return (
            await api.patch(`/api/admin-api/settings/services/${encodeURIComponent(service.code)}`, {
                is_enabled: service.is_enabled,
                maintenance_message: service.maintenance_message,
                name: service.name,
                sort_order: service.sort_order,
                icon_type: service.icon_type,
                icon: service.icon,
                image_url: service.image_url,
                description: service.description,
                url: service.url || null,
            })
        ).data.data;
    },
    async create(service: ManagedService): Promise<ManagedService> {
        return (await api.post('/api/admin-api/settings/services', service)).data.data;
    },
    async remove(code: string): Promise<void> {
        await api.delete(`/api/admin-api/settings/services/${encodeURIComponent(code)}`);
    },
};
