import api from '@/config/axios';

export type ServicePayloadField = {
    name: string;
    label: string;
    type: 'text' | 'textarea' | 'password' | 'email' | 'number' | 'boolean' | 'select';
    required: boolean;
    placeholder?: string | null;
    options?: { value: string; label: string }[];
};

export type ServiceOffering = {
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
    page_slug?: string | null;
    payload_fields: ServicePayloadField[];
};

export const adminServiceCatalog = {
    async list(): Promise<ServiceOffering[]> {
        return (await api.get('/api/admin-api/settings/service-catalog')).data.data;
    },
    async update(service: ServiceOffering): Promise<ServiceOffering> {
        return (
            await api.patch(`/api/admin-api/settings/service-catalog/${encodeURIComponent(service.code)}`, {
                is_enabled: service.is_enabled,
                maintenance_message: service.maintenance_message,
                name: service.name,
                sort_order: service.sort_order,
                icon_type: service.icon_type,
                icon: service.icon,
                image_url: service.image_url,
                description: service.description,
                page_slug: service.page_slug || null,
                url: null,
                payload_fields: service.payload_fields,
            })
        ).data.data;
    },
    async create(service: ServiceOffering): Promise<ServiceOffering> {
        return (await api.post('/api/admin-api/settings/service-catalog', service)).data.data;
    },
    async remove(code: string): Promise<void> {
        await api.delete(`/api/admin-api/settings/service-catalog/${encodeURIComponent(code)}`);
    },
};
