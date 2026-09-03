import api from '@/config/axios';

export const adminTenantService = {
    list: (params: Record<string, unknown> = {}) => api.get('/api/admin-api/tenants', { params }),
    create: (payload: Record<string, unknown>) => api.post('/api/admin-api/tenants', payload),
    update: (id: number, payload: Record<string, unknown>) => api.put(`/api/admin-api/tenants/${id}`, payload),
    current: () => api.get('/api/admin-api/site'),
    prices: () => api.get('/api/admin-api/site/prices'),
    updatePrice: (packageId: number, payload: Record<string, unknown>) => api.put(`/api/admin-api/site/prices/${packageId}`, payload),
};
