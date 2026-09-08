import axios from '@/config/axios';

const root = '/api/admin-api';

export const adminTopupService = {
    providers: (params = {}) => axios.get(`${root}/topup-providers`, { params }),
    provider: (id: number) => axios.get(`${root}/topup-providers/${id}`),
    saveProvider: (id: number | null, payload: Record<string, unknown>) =>
        id ? axios.put(`${root}/topup-providers/${id}`, payload) : axios.post(`${root}/topup-providers`, payload),
    deleteProvider: (id: number) => axios.delete(`${root}/topup-providers/${id}`),
    refreshProviderBalances: (providerIds: number[]) => axios.post(`${root}/topup-providers/refresh-balances`, { provider_ids: providerIds }),
    games: (params = {}) => axios.get(`${root}/games`, { params }),
    saveGame: (id: number | null, payload: Record<string, unknown>) =>
        id ? axios.put(`${root}/games/${id}`, payload) : axios.post(`${root}/games`, payload),
    deleteGame: (id: number) => axios.delete(`${root}/games/${id}`),
    globalPackages: () => axios.get(`${root}/global-topup-packages`),
    servers: (params = {}) => axios.get(`${root}/game-servers`, { params }),
    saveServer: (id: number | null, payload: Record<string, unknown>) =>
        id ? axios.put(`${root}/game-servers/${id}`, payload) : axios.post(`${root}/game-servers`, payload),
    deleteServer: (id: number) => axios.delete(`${root}/game-servers/${id}`),
    packages: (params = {}) => axios.get(`${root}/topup-packages`, { params }),
    savePackage: (id: number | null, payload: Record<string, unknown>) =>
        id ? axios.put(`${root}/topup-packages/${id}`, payload) : axios.post(`${root}/topup-packages`, payload),
    deletePackage: (id: number) => axios.delete(`${root}/topup-packages/${id}`),
    orders: (params = {}) => axios.get(`${root}/orders`, { params }),
    order: (code: string) => axios.get(`${root}/orders/${code}`),
    updateOrder: (code: string, action: string, reason?: string, forceReorder = false) =>
        axios.put(`${root}/orders/${code}`, { action, reason, force_reorder: forceReorder || undefined }),
};
