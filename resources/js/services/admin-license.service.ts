import api from '@/config/axios';
export type LicenseProduct = {
    id: number;
    name: string;
    product_code: string;
    description: string | null;
    minimum_version: string;
    latest_version?: string | null;
    tool_zip_name?: string | null;
    update_zip_name?: string | null;
    tool_zip_size?: number | null;
    update_zip_size?: number | null;
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
    key?: string;
    status: string;
    user_id: number | null;
    duration_days: number | null;
    transfer_cooldown: number;
    created_at: string;
    owner?: { id: number; username: string; email: string } | null;
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
    async saveProduct(
        value: Omit<LicenseProduct, 'id'>,
        id: number | null,
        files: { tool: File | null; update: File | null } = { tool: null, update: null },
    ) {
        const body = new FormData();
        const fields = [
            'name',
            'product_code',
            'description',
            'minimum_version',
            'latest_version',
            'is_active',
            'heartbeat_interval',
            'lease_duration',
            'transfer_cooldown',
            'max_active_devices',
            'offline_grace',
        ] as const;
        fields.forEach((field) => {
            const item = value[field];
            body.append(field, typeof item === 'boolean' ? (item ? '1' : '0') : String(item ?? ''));
        });
        if (files.tool) body.append('tool_zip', files.tool);
        if (files.update) body.append('update_zip', files.update);
        if (id) body.append('_method', 'PATCH');
        return api.post(id ? `${endpoint}/products/${id}` : `${endpoint}/products`, body);
    },
    async savePlan(value: Omit<LicensePlan, 'id'>, id: number | null) {
        return id ? api.patch(`${endpoint}/plans/${id}`, value) : api.post(`${endpoint}/plans`, value);
    },
    async deleteProduct(id: number) {
        return api.delete(`${endpoint}/products/${id}`);
    },
    async deletePlan(id: number) {
        return api.delete(`${endpoint}/plans/${id}`);
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
    async edit(
        id: number,
        value: { user_id: number | null; duration_days: number | null; expires_at: string | null; transfer_cooldown: number; reason: string },
    ) {
        return api.patch(`${endpoint}/keys/${id}`, { action: 'edit', ...value });
    },
    async remove(id: number, reason: string) {
        return api.delete(`${endpoint}/keys/${id}`, { data: { reason } });
    },
    async manage(id: number, value: { action: string; reason: string; days: number | null; device_uuid: string | null }) {
        return (await api.patch(`${endpoint}/keys/${id}`, value)).data.data;
    },
};
