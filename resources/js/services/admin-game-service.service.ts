import api from '@/config/axios';

export type Status = 'active' | 'inactive';

export interface GameServiceGame {
    id: number;
    name: string;
    slug: string;
    code: string | null;
    image: string | null;
    status: Status;
    game_services_enabled: boolean;
    servers_count: number;
    game_services_count: number;
}

export interface PayloadOption {
    value: string;
    text: string;
}

export interface PayloadField {
    key: string;
    label: string;
    placeholder: string;
    required: boolean;
    regex: string;
    type: 'text' | 'number' | 'password' | 'select';
    options: PayloadOption[];
    min: number | null;
    max: number | null;
    step: number | null;
}

export interface GameServerOption {
    id: number;
    game_id: number;
    name: string;
    code: string | null;
}

export interface GameServiceItem {
    id: number;
    game_id: number;
    game: { id: number; name: string; slug: string; code: string | null };
    name: string;
    slug: string;
    code: string;
    description: string | null;
    background_image: string | null;
    payload_fields: PayloadField[];
    seo_content: unknown[];
    faqs: Array<{ question: string; answer: string }>;
    status: Status;
    sort_order: number;
    server_ids: number[];
    servers: GameServerOption[];
    packages_count: number;
    orders_count: number;
}

export interface GameServicePrice {
    id?: number;
    code: string;
    price: number;
    collaborator_price: number;
    quantity_enabled: boolean;
    min_quantity: number;
    max_quantity: number;
    status: Status;
    sort_order: number;
}

export interface GameServicePackage {
    id: number;
    game_service_id: number;
    service: { id: number; name: string; game_id: number; game_name: string };
    name: string;
    code: string;
    description: string | null;
    status: Status;
    sort_order: number;
    prices: GameServicePrice[];
    orders_count: number;
}

export type GameServiceOrderStatus = 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';

export interface GameServiceOrder {
    id: number;
    code: string;
    email: string | null;
    game_id: number | null;
    game_service_id: number | null;
    game_name: string;
    service_name: string;
    package_name: string;
    price_label: string;
    server_name: string | null;
    payload: Record<string, unknown>;
    quantity: number;
    unit_price: number;
    total_amount: number;
    status: GameServiceOrderStatus;
    admin_note: string | null;
    processing_at: string | null;
    completed_at: string | null;
    created_at: string;
}

export const adminGameServiceService = {
    games: (params: Record<string, unknown> = {}) => api.get('/api/admin-api/game-service-games', { params }),
    updateGame: (id: number, payload: Record<string, unknown>) => api.patch(`/api/admin-api/game-service-games/${id}`, payload),
    servers: (params: Record<string, unknown> = {}) => api.get('/api/admin-api/game-servers', { params }),
    services: (params: Record<string, unknown> = {}) => api.get('/api/admin-api/game-services', { params }),
    saveService: (id: number | null, payload: Record<string, unknown>) =>
        id ? api.put(`/api/admin-api/game-services/${id}`, payload) : api.post('/api/admin-api/game-services', payload),
    deleteService: (id: number) => api.delete(`/api/admin-api/game-services/${id}`),
    packages: (params: Record<string, unknown> = {}) => api.get('/api/admin-api/game-service-packages', { params }),
    savePackage: (id: number | null, payload: Record<string, unknown>) =>
        id ? api.put(`/api/admin-api/game-service-packages/${id}`, payload) : api.post('/api/admin-api/game-service-packages', payload),
    deletePackage: (id: number) => api.delete(`/api/admin-api/game-service-packages/${id}`),
    orders: (params: Record<string, unknown> = {}) => api.get('/api/admin-api/game-service-orders', { params }),
    order: (code: string) => api.get(`/api/admin-api/game-service-orders/${code}`),
    updateOrder: (code: string, payload: Record<string, unknown>) => api.patch(`/api/admin-api/game-service-orders/${code}`, payload),
};
