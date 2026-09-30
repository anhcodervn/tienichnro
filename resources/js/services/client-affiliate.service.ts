import api from '@/config/axios';

export type ClientAffiliateAnnouncement = {
    id: number;
    title: string;
    content_html: string;
    is_pinned: boolean;
    is_read: boolean;
    published_at: string | null;
};

export type ClientAffiliateHomeData = {
    announcements: ClientAffiliateAnnouncement[];
    unread_count: number;
};

export type ClientAffiliateRate = {
    package_id: number;
    game: string;
    package: string;
    denomination: number;
    selling_price: number;
    commission_type: 'fixed' | 'percentage';
    fixed_amount: number | null;
    percentage: number | null;
    estimated_commission: number;
    source: 'package' | 'global';
};

export type ClientAffiliateRatesData = {
    rates: ClientAffiliateRate[];
};

export type ClientAffiliateData = {
    program: { minimum_withdrawal: number; minimum_conversion: number; holding_days: number };
    profile: {
        status: 'active' | 'suspended';
        bank_name: string | null;
        bank_account_name: string | null;
        bank_account_number_masked: string;
        has_payout_account: boolean;
    };
    referral: { code: string; url: string; referrals_count: number; orders_count: number; guest_orders_count: number };
    wallets: { affiliate: { balance: number; hold_balance: number }; main: { balance: number } };
    stats: { pending: number; available_earned: number; reversed: number; revenue: number };
    commissions: Array<{
        id: number;
        amount: number;
        base_amount: number;
        status: string;
        available_at: string | null;
        order: { code: string } | null;
        referred_user: { username: string } | null;
        package: { name: string } | null;
    }>;
    referrals: Array<{ id: number; username: string; created_at: string }>;
    withdrawals: Array<{ id: number; amount: number; status: string; bank_name: string; account_number: string; created_at: string }>;
    conversions: Array<{ id: number; amount: number; status: string; created_at: string }>;
};

const root = '/api/client/affiliate';

export type GameServiceChatMessage = {
    id: number;
    sender_role: 'user' | 'collaborator' | 'admin';
    sender_name: string;
    message: string;
    progress: { id: number; type: 'progress' | 'completion'; image_url: string | null } | null;
    created_at: string;
};

export type GameServiceChatOrder = {
    id: number;
    code: string;
    service_name: string;
    package_name: string;
    status: CollaboratorOrder['status'];
    user: { id: number; name: string; email: string } | null;
    collaborator: { id: number; name: string } | null;
    messages_count: number;
    last_message: GameServiceChatMessage | null;
    created_at: string;
};

export type GameServiceChatThread = {
    order: GameServiceChatOrder;
    messages: GameServiceChatMessage[];
};

export type GameServiceOrderProgress = {
    id: number;
    type: 'progress' | 'completion';
    description: string;
    image_url: string | null;
    author: { id: number; name: string; role: string } | null;
    created_at: string;
};

export type CollaboratorOrder = {
    code: string;
    game_id: number | null;
    game_name: string;
    service_name: string;
    package_name: string;
    server_name: string | null;
    payload: Record<string, string | number | null>;
    payload_locked: boolean;
    quantity: number;
    status: 'pending' | 'processing' | 'review' | 'completed' | 'failed' | 'cancelled';
    collaborator_id: number | null;
    collaborator_amount: number | null;
    can_claim: boolean;
    can_chat: boolean;
    settled_at: string | null;
    available_at: string | null;
    refunded_at: string | null;
    created_at: string;
};

export type CollaboratorOrderPreview = CollaboratorOrder & {
    customer_note: string | null;
};

export type CollaboratorDashboardData = {
    orders: { pending: number; processing: number; review: number; completed: number; total: number };
    revenue: { order_revenue: number; held: number; settled: number; available: number; withdrawal_hold: number };
    unread_announcements: number;
    recent_orders: CollaboratorOrder[];
};

export type CollaboratorOrdersData = {
    data: CollaboratorOrder[];
    games: Array<{ id: number; name: string }>;
    meta: { current_page: number; last_page: number; total: number };
};

export type CollaboratorFinanceData = {
    minimum_withdrawal: number;
    wallet: { balance: number; hold_balance: number; work_hold_balance: number };
    profile: {
        status: 'active' | 'suspended';
        bank_name: string | null;
        bank_account_name: string | null;
        bank_account_number_masked: string;
        has_payout_account: boolean;
    };
    withdrawals: Array<{ id: number; amount: number; status: string; bank_name: string; account_number: string; created_at: string }>;
};

export type CollaboratorWalletHistoryData = {
    wallet: { balance: number; hold_balance: number; work_hold_balance: number };
    data: Array<{
        id: number;
        type: string;
        event: string | null;
        amount: number;
        balance_before: number;
        balance_after: number;
        work_hold_before: number | null;
        work_hold_after: number | null;
        order_code: string | null;
        description: string;
        created_at: string;
    }>;
    meta: { current_page: number; last_page: number; total: number };
};

export const clientAffiliateService = {
    home: async (): Promise<ClientAffiliateHomeData> => (await api.get(`${root}/home`)).data.data,
    data: async (): Promise<ClientAffiliateData> => (await api.get(root)).data.data,
    rates: async (): Promise<ClientAffiliateRatesData> => (await api.get(`${root}/rates`)).data.data,
    updatePayout: (payload: { bank_name: string; bank_account_name: string; bank_account_number: string }) =>
        api.put(`${root}/payout-account`, payload),
    convert: (amount: number, idempotencyKey: string) => api.post(`${root}/convert`, { amount, idempotency_key: idempotencyKey }),
    withdraw: (amount: number, idempotencyKey: string) => api.post(`${root}/withdrawals`, { amount, idempotency_key: idempotencyKey }),
    collaboratorDashboard: async (): Promise<CollaboratorDashboardData> => (await api.get(`${root}/game-service-dashboard`)).data.data,
    collaboratorAnnouncements: async (): Promise<ClientAffiliateHomeData> => (await api.get(`${root}/game-service-announcements`)).data.data,
    readCollaboratorAnnouncement: async (id: number): Promise<number> =>
        (await api.post(`${root}/game-service-announcements/${id}/read`)).data.data.unread_count,
    collaboratorFinance: async (): Promise<CollaboratorFinanceData> => (await api.get(`${root}/game-service-finance`)).data.data,
    collaboratorWalletHistory: async (page = 1): Promise<CollaboratorWalletHistoryData> =>
        (await api.get(`${root}/game-service-wallet-history`, { params: { page } })).data.data,
    updateCollaboratorPayout: (payload: { bank_name: string; bank_account_name: string; bank_account_number: string }) =>
        api.put(`${root}/game-service-payout-account`, payload),
    withdrawCollaborator: (amount: number, idempotencyKey: string) =>
        api.post(`${root}/game-service-withdrawals`, { amount, idempotency_key: idempotencyKey }),
    gameServiceOrders: async (params: Record<string, unknown> = {}): Promise<CollaboratorOrdersData> =>
        (await api.get(`${root}/game-service-orders`, { params })).data.data,
    gameServiceOrderPayload: async (code: string): Promise<Record<string, string | number | null>> =>
        (await api.get(`${root}/game-service-orders/${code}/payload`)).data.data.payload,
    gameServiceOrderPreview: async (code: string): Promise<CollaboratorOrderPreview> =>
        (await api.get(`${root}/game-service-orders/${code}/preview`)).data.data,
    gameServiceOrderChats: async (): Promise<GameServiceChatOrder[]> => (await api.get(`${root}/game-service-order-chats`)).data.data,
    startGameServiceOrder: (code: string) => api.post(`${root}/game-service-orders/${code}/start`),
    gameServiceOrderProgress: async (code: string): Promise<GameServiceOrderProgress[]> =>
        (await api.get(`${root}/game-service-orders/${code}/progress`)).data.data.progress,
    storeGameServiceOrderProgress: (code: string, payload: FormData) => api.post(`${root}/game-service-orders/${code}/progress`, payload),
    completeGameServiceOrder: (code: string, payload: FormData) => api.post(`${root}/game-service-orders/${code}/submit`, payload),
    gameServiceOrderThread: async (code: string): Promise<GameServiceChatThread> =>
        (await api.get(`${root}/game-service-orders/${code}/messages`)).data.data,
    sendGameServiceOrderMessage: async (code: string, message: string): Promise<GameServiceChatMessage> =>
        (await api.post(`${root}/game-service-orders/${code}/messages`, { message })).data.data,
    readAnnouncement: async (id: number): Promise<number> => (await api.post(`${root}/announcements/${id}/read`)).data.data.unread_count,
};
