import api from '@/config/axios';

export type ClientAffiliateAnnouncement = {
    id: number;
    title: string;
    content_html: string;
    is_pinned: boolean;
    published_at: string | null;
};

export type ClientAffiliateHomeData = {
    announcements: ClientAffiliateAnnouncement[];
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

export const clientAffiliateService = {
    home: async (): Promise<ClientAffiliateHomeData> => (await api.get(`${root}/home`)).data.data,
    data: async (): Promise<ClientAffiliateData> => (await api.get(root)).data.data,
    updatePayout: (payload: { bank_name: string; bank_account_name: string; bank_account_number: string }) =>
        api.put(`${root}/payout-account`, payload),
    convert: (amount: number, idempotencyKey: string) => api.post(`${root}/convert`, { amount, idempotency_key: idempotencyKey }),
    withdraw: (amount: number, idempotencyKey: string) => api.post(`${root}/withdrawals`, { amount, idempotency_key: idempotencyKey }),
};
