import api from '@/config/axios';
export type LicenseProduct = {
    id: number;
    name: string;
    product_code: string;
    description: string | null;
    minimum_version: string;
    is_active: boolean;
    heartbeat_interval: number;
    lease_duration: number;
    transfer_cooldown: number;
    max_active_devices: number;
    offline_grace: number;
};
export type LicensePlan = {
    id: number;
    product_id: number;
    name: string;
    duration_days: number | null;
    price: number;
    is_active: boolean;
    max_active_devices: number;
    transfer_cooldown: number;
};
export type LicenseKey = {
    id: number;
    product_name: string;
    plan_name: string;
    key_prefix: string;
    status: string;
    user_id: number | null;
    online: boolean;
    activated_at: string | null;
    expires_at: string | null;
    current_device_uuid: string | null;
    generation: number;
    devices?: { device_uuid: string; device_name: string; last_seen_at: string; assurance: string }[];
    sessions?: { id: string; status: string; generation: number; lease_expires_at: string; revocation_reason: string | null }[];
    events?: { id: number; event: string; reason: string | null; created_at: string }[];
};
const endpoint = '/api/admin-api/license';
export const adminLicense = {
    async products(): Promise<LicenseProduct[]> {
        return (await api.get(`${endpoint}/products`)).data.data;
    },
    async plans(): Promise<LicensePlan[]> {
        return (await api.get(`${endpoint}/plans`)).data.data;
    },
    async saveProduct(value: Omit<LicenseProduct, 'id'>, id: number | null) {
        return id ? api.patch(`${endpoint}/products/${id}`, value) : api.post(`${endpoint}/products`, value);
    },
    async savePlan(value: Omit<LicensePlan, 'id'>, id: number | null) {
        return id ? api.patch(`${endpoint}/plans/${id}`, value) : api.post(`${endpoint}/plans`, value);
    },
    async keys(params: {
        page: number;
        search: string;
        status: string;
        product_id: string;
    }): Promise<{ data: LicenseKey[]; meta: { last_page: number; total: number } }> {
        return (await api.get(`${endpoint}/keys`, { params })).data;
    },
    async issue(value: { plan_id: number; user_id: number | null; quantity: number }): Promise<{ id: number; key: string }[]> {
        return (await api.post(`${endpoint}/keys`, value)).data.data;
    },
    async detail(id: number): Promise<LicenseKey> {
        return (await api.get(`${endpoint}/keys/${id}`)).data.data;
    },
    async manage(id: number, value: { action: string; reason: string; days: number | null; device_uuid: string | null }) {
        return api.patch(`${endpoint}/keys/${id}`, value);
    },
};
