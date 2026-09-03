import api from '@/config/axios';
import type { ApiBankVnBankAccountOption, RechargeBonusTierType, RechargeConfigType } from '@/types/recharge-config.type';

type RechargeConfigPayload = {
    provider: 'manual' | 'apibankvn_api';
    bank_name: string;
    account_name: string;
    account_number: string;
    qr_template: string;
    transfer_prefix: string;
    api_base_url: string | null;
    api_key: string | null;
    api_secret: string | null;
    webhook_secret: string | null;
    api_bank_id: number | null;
    is_active: boolean;
};

type RechargeBonusTierPayload = {
    minimum_amount: number;
    bonus_percent: number;
    is_active: boolean;
};

export type RechargeConfigPageData = {
    configs: RechargeConfigType[];
    site: {
        is_main: boolean;
        callback_url: string;
        allowed_providers: Array<'manual' | 'apibankvn_api'>;
    };
};

export const adminRechargeConfigService = {
    async get(): Promise<RechargeConfigPageData> {
        const response = await api.get('/api/admin-api/recharge-config');

        return response.data.data as RechargeConfigPageData;
    },

    async create(payload: RechargeConfigPayload): Promise<{ config: RechargeConfigType }> {
        const response = await api.post('/api/admin-api/recharge-config', payload);

        return response.data.data as { config: RechargeConfigType };
    },

    async update(id: number, payload: RechargeConfigPayload): Promise<{ config: RechargeConfigType }> {
        const response = await api.patch(`/api/admin-api/recharge-config/${id}`, payload);

        return response.data.data as { config: RechargeConfigType };
    },

    async toggle(id: number): Promise<{ config: RechargeConfigType }> {
        const response = await api.patch(`/api/admin-api/recharge-config/${id}/toggle`);

        return response.data.data as { config: RechargeConfigType };
    },

    async remove(id: number): Promise<void> {
        await api.delete(`/api/admin-api/recharge-config/${id}`);
    },

    async getBonusTiers(): Promise<RechargeBonusTierType[]> {
        const response = await api.get('/api/admin-api/recharge-bonus-tiers');

        return (response.data.data?.tiers ?? []) as RechargeBonusTierType[];
    },

    async createBonusTier(payload: RechargeBonusTierPayload): Promise<{ tier: RechargeBonusTierType }> {
        const response = await api.post('/api/admin-api/recharge-bonus-tiers', payload);

        return response.data.data as { tier: RechargeBonusTierType };
    },

    async updateBonusTier(id: number, payload: RechargeBonusTierPayload): Promise<{ tier: RechargeBonusTierType }> {
        const response = await api.put(`/api/admin-api/recharge-bonus-tiers/${id}`, payload);

        return response.data.data as { tier: RechargeBonusTierType };
    },

    async removeBonusTier(id: number): Promise<void> {
        await api.delete(`/api/admin-api/recharge-bonus-tiers/${id}`);
    },

    async verifyCredentials(payload: { api_key: string; api_secret: string }): Promise<{
        user: Record<string, unknown>;
        permissions: unknown[];
        endpoints: string[];
        bank_accounts: ApiBankVnBankAccountOption[];
    }> {
        const response = await api.post('/api/admin-api/recharge-config/verify-credentials', payload);

        return response.data.data as {
            user: Record<string, unknown>;
            permissions: unknown[];
            endpoints: string[];
            bank_accounts: ApiBankVnBankAccountOption[];
        };
    },
};
