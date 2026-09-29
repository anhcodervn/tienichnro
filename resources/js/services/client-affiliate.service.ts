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
    quantity: number;
    status: 'pending' | 'processing' | 'review' | 'completed' | 'failed' | 'cancelled';
    collaborator_amount: number | null;
    settled_at: string | null;
    created_at: string;
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
    wallet: { balance: number; hold_balance: number };
    profile: {
        status: 'active' | 'suspended';
        bank_name: string | null;
        bank_account_name: string | null;
        bank_account_number_masked: string;
        has_payout_account: boolean;
    };
    withdrawals: Array<{ id: number; amount: number; status: string; bank_name: string; account_number: string; created_at: string }>;
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
    collaboratorFinance: async (): Promise<CollaboratorFinanceData> => (await api.get(`${root}/game-service-finance`)).data.data,
    updateCollaboratorPayout: (payload: { bank_name: string; bank_account_name: string; bank_account_number: string }) =>
        api.put(`${root}/game-service-payout-account`, payload),
    withdrawCollaborator: (amount: number, idempotencyKey: string) =>
        api.post(`${root}/game-service-withdrawals`, { amount, idempotency_key: idempotencyKey }),
    gameServiceOrders: async (params: Record<string, unknown> = {}): Promise<CollaboratorOrdersData> =>
        (await api.get(`${root}/game-service-orders`, { params })).data.data,
    startGameServiceOrder: (code: string) => api.post(`${root}/game-service-orders/${code}/start`),
    submitGameServiceOrder: (code: string) => api.post(`${root}/game-service-orders/${code}/submit`),
    gameServiceOrderThread: async (code: string) => (await api.get(`${root}/game-service-orders/${code}/messages`)).data.data,
    sendGameServiceOrderMessage: async (code: string, message: string): Promise<GameServiceChatMessage> =>
        (await api.post(`${root}/game-service-orders/${code}/messages`, { message })).data.data,
    readAnnouncement: async (id: number): Promise<number> => (await api.post(`${root}/announcements/${id}/read`)).data.data.unread_count,
};
