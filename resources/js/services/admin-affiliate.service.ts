import api from '@/config/axios';

export type AffiliateOverview = {
    partners: { total: number; active: number; suspended: number };
    commissions: { orders: number; guest_orders: number; pending: number; available: number; reversed: number; flagged: number; revenue: number };
    withdrawals: { requested: number; approved: number; paid: number };
    by_site: Array<{
        tenant_id: number;
        site: string;
        is_enabled: boolean;
        partners_count: number;
        active_partners: number;
        commissions_count: number;
        revenue: number;
        commission_cost: number;
        pending_withdrawal: number;
    }>;
    selected_site_id: number | null;
};

export type AffiliateRate = {
    package_id: number;
    global_package_id: number | null;
    is_global: boolean;
    mode: 'global' | 'override' | 'disabled' | 'none';
    effective_source: 'global' | 'package' | null;
    game: string;
    package: string;
    denomination: number;
    selling_price: number;
    margin: number;
    commission_type: 'fixed' | 'percentage';
    fixed_amount: number;
    percentage: number;
    is_active: boolean;
};

export type AffiliateGlobalRate = {
    global_package_id: number;
    package: string;
    denomination: number;
    games: string[];
    minimum_margin: number;
    commission_type: 'fixed' | 'percentage';
    fixed_amount: number;
    percentage: number;
    is_active: boolean;
};

export type AffiliateConfiguration = {
    site: { id: number; name: string; is_main: boolean };
    program: { is_enabled: boolean; minimum_withdrawal: number; holding_days: number; minimum_conversion: number };
    global_rates: AffiliateGlobalRate[];
    rates: AffiliateRate[];
    sites: Array<{ id: number; name: string }>;
};

export type Paginated<T> = { data: T[]; current_page: number; last_page: number; total: number };

export type AffiliatePartner = {
    id: number;
    tenant_id: number;
    status: 'active' | 'suspended';
    user: { id: number; username: string; email: string; referral_code: string };
    referrals_count: number;
    orders_count: number;
    revenue: number;
    pending: number;
    available: number;
    wallet_balance: number;
    hold_balance: number;
};

export type AffiliateCommission = {
    id: number;
    tenant_id: number;
    amount: number;
    wallet_type: 'affiliate' | 'collaborator';
    base_amount: number;
    status: string;
    commission_type: string;
    rate_value: number;
    is_flagged: boolean;
    hold_reason: string | null;
    available_at: string | null;
    order: { code: string } | null;
    referrer: { username: string } | null;
    referred_user: { username: string } | null;
    package: { name: string } | null;
};

export type AffiliateWithdrawal = {
    id: number;
    tenant_id: number;
    amount: number;
    status: string;
    bank_name: string;
    bank_account_name: string;
    bank_account_number_masked: string;
    bank_transaction_reference: string | null;
    user: { username: string; email: string } | null;
    created_at: string;
};

export type AffiliateAnnouncement = {
    id: number;
    tenant_id: number;
    audience: 'affiliate' | 'collaborator';
    title: string;
    content: unknown[];
    content_html: string;
    is_pinned: boolean;
    is_published: boolean;
    published_at: string | null;
    updated_at: string | null;
    admin: { id: number; username: string; full_name: string | null } | null;
};

export type AffiliateAnnouncementData = {
    announcements: AffiliateAnnouncement[];
    sites: Array<{ id: number; name: string }>;
    selected_site_id: number;
};

const root = '/api/admin-api/affiliate';
const collaboratorAnnouncementRoot = '/api/admin-api/game-service-announcements';

export const adminAffiliateService = {
    overview: async (params: Record<string, unknown> = {}): Promise<AffiliateOverview> => (await api.get(root, { params })).data.data,
    configuration: async (siteId?: number): Promise<AffiliateConfiguration> =>
        (await api.get(`${root}/configuration`, { params: siteId ? { site_id: siteId } : {} })).data.data,
    updateProgram: (payload: Record<string, unknown>) => api.put(`${root}/configuration`, payload),
    updateGlobalRate: (globalPackageId: number, payload: Record<string, unknown>) => api.put(`${root}/global-rates/${globalPackageId}`, payload),
    updateRate: (packageId: number, payload: Record<string, unknown>) => api.put(`${root}/rates/${packageId}`, payload),
    resetRate: (packageId: number, siteId: number) => api.delete(`${root}/rates/${packageId}`, { params: { site_id: siteId } }),
    partners: async (params: Record<string, unknown> = {}): Promise<Paginated<AffiliatePartner>> =>
        (await api.get(`${root}/partners`, { params })).data.data,
    updatePartner: (profileId: number, payload: Record<string, unknown>) => api.patch(`${root}/partners/${profileId}`, payload),
    assignPartnerOrder: (profileId: number, orderCode: string) => api.post(`${root}/partners/${profileId}/orders`, { order_code: orderCode }),
    commissions: async (params: Record<string, unknown> = {}): Promise<Paginated<AffiliateCommission>> =>
        (await api.get(`${root}/commissions`, { params })).data.data,
    updateCommission: (commissionId: number, payload: Record<string, unknown>) => api.patch(`${root}/commissions/${commissionId}`, payload),
    withdrawals: async (params: Record<string, unknown> = {}): Promise<Paginated<AffiliateWithdrawal>> =>
        (await api.get(`${root}/withdrawals`, { params })).data.data,
    withdrawal: async (withdrawalId: number) => (await api.get(`${root}/withdrawals/${withdrawalId}`)).data.data,
    updateWithdrawal: (withdrawalId: number, payload: Record<string, unknown>) => api.patch(`${root}/withdrawals/${withdrawalId}`, payload),
    announcements: async (siteId?: number): Promise<AffiliateAnnouncementData> =>
        (await api.get(`${root}/announcements`, { params: siteId ? { site_id: siteId } : {} })).data.data,
    createAnnouncement: (payload: Record<string, unknown>) => api.post(`${root}/announcements`, payload),
    updateAnnouncement: (announcementId: number, payload: Record<string, unknown>) => api.put(`${root}/announcements/${announcementId}`, payload),
    deleteAnnouncement: (announcementId: number) => api.delete(`${root}/announcements/${announcementId}`),
    collaboratorAnnouncements: async (siteId?: number): Promise<AffiliateAnnouncementData> =>
        (await api.get(collaboratorAnnouncementRoot, { params: siteId ? { site_id: siteId } : {} })).data.data,
    createCollaboratorAnnouncement: (payload: Record<string, unknown>) => api.post(collaboratorAnnouncementRoot, payload),
    updateCollaboratorAnnouncement: (announcementId: number, payload: Record<string, unknown>) =>
        api.put(`${collaboratorAnnouncementRoot}/${announcementId}`, payload),
    deleteCollaboratorAnnouncement: (announcementId: number) => api.delete(`${collaboratorAnnouncementRoot}/${announcementId}`),
};
