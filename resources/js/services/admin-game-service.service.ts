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

export type GameServiceOrderStatus = 'pending' | 'processing' | 'review' | 'completed' | 'failed' | 'cancelled';

export interface GameServiceOrderSettlement {
    approved_orders: number;
    settled_orders: number;
    revenue: number;
    collaborator_cost: number;
    gross_profit: number;
    estimated_tax: number;
    net_profit: number;
    loss_orders: number;
    legacy_orders: number;
}

export interface GameServiceOrderProgress {
    id: number;
    type: 'progress' | 'completion';
    description: string;
    image_url: string | null;
    author: { id: number; name: string; role: string } | null;
    created_at: string;
}

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
    payload_locked: boolean;
    quantity: number;
    unit_price: number;
    total_amount: number;
    collaborator_unit_cost: number | null;
    collaborator_total_cost: number | null;
    gross_profit: number | null;
    tax_enabled: boolean | null;
    tax_calculation_type: 'revenue' | null;
    vat_rate: number | null;
    pit_rate: number | null;
    estimated_vat: number | null;
    estimated_pit: number | null;
    estimated_tax: number | null;
    net_profit: number | null;
    profit_margin: number | null;
    status: GameServiceOrderStatus;
    collaborator_id: number | null;
    collaborator: { id: number; name: string } | null;
    admin_note: string | null;
    processing_at: string | null;
    completed_at: string | null;
    collaborator_held_at: string | null;
    collaborator_available_at: string | null;
    collaborator_settled_at: string | null;
    collaborator_refunded_at: string | null;
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
    reviewOrderCount: async (): Promise<number> => Number((await api.get('/api/admin-api/game-service-orders/review-count')).data.data.count ?? 0),
    order: (code: string) => api.get(`/api/admin-api/game-service-orders/${code}`),
    orderPayload: async (code: string): Promise<Record<string, unknown>> =>
        (await api.get(`/api/admin-api/game-service-orders/${code}/payload`)).data.data.payload,
    orderProgress: async (code: string): Promise<GameServiceOrderProgress[]> =>
        (await api.get(`/api/admin-api/game-service-orders/${code}/progress`)).data.data.progress,
    updateOrder: (code: string, payload: Record<string, unknown>) => api.patch(`/api/admin-api/game-service-orders/${code}`, payload),
    approveOrderCompletion: (code: string, payload: { admin_note: string | null }) =>
        api.patch(`/api/admin-api/game-service-orders/${code}/approve-completion`, payload),
    refundOrder: (code: string, payload: { status: 'cancelled' | 'failed'; admin_note: string }) =>
        api.patch(`/api/admin-api/game-service-orders/${code}/refund`, payload),
    chatCollaborators: (gameServiceId?: number | null, includeUserId?: number | null) =>
        api.get('/api/admin-api/game-service-order-chats/collaborators', {
            params: {
                ...(gameServiceId ? { game_service_id: gameServiceId } : {}),
                ...(includeUserId ? { include_user_id: includeUserId } : {}),
            },
        }),
    chatThreads: (params: Record<string, unknown> = {}) => api.get('/api/admin-api/game-service-order-chats', { params }),
    chatThread: (code: string) => api.get(`/api/admin-api/game-service-order-chats/${code}`),
    sendChatMessage: (code: string, message: string) => api.post(`/api/admin-api/game-service-order-chats/${code}/messages`, { message }),
};
