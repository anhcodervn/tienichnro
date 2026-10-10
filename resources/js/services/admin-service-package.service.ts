import api from '@/config/axios';

export type ServicePackage = {
    id: number;
    service_code: string;
    name: string;
    description: string | null;
    price: number;
    billing_type: 'usage' | 'time' | 'lifetime';
    usage_limit: number | null;
    duration_days: number | null;
    is_active: boolean;
    sort_order: number;
};
export type PackageDraft = Omit<ServicePackage, 'id'>;
const endpoint = '/api/admin-api/settings/service-packages';
export const adminServicePackages = {
    async list(): Promise<ServicePackage[]> {
        return (await api.get(endpoint)).data.data;
    },
    async save(draft: PackageDraft, id: number | null): Promise<ServicePackage> {
        return (id === null ? await api.post(endpoint, draft) : await api.patch(`${endpoint}/${id}`, draft)).data.data;
    },
    async remove(id: number): Promise<void> {
        await api.delete(`${endpoint}/${id}`);
    },
};
