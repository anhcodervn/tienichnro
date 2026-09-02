import api from '@/config/axios';

export type GlobalRewardReceive = {
    code: string;
    label: string;
    base_amount: number;
    reward_x2_amount: number | null;
    reward_x3_amount: number | null;
    first_topup_reward_amount: number | null;
};

export type GlobalRewardGameSetting = {
    id: number;
    game_id: number;
    denomination: number;
    receives: GlobalRewardReceive[];
};

export type GlobalRewardGame = {
    id: number;
    name: string;
    slug: string;
    status: 'active' | 'inactive';
    reward_settings: GlobalRewardGameSetting[];
    denominations: GlobalRewardDenomination[];
};

export type GlobalRewardDenomination = {
    name: string;
    denomination: number;
    status: 'active' | 'inactive';
};

export type GlobalRewardCatalog = {
    games: GlobalRewardGame[];
};

const root = '/api/admin-api/global-topup-rewards';

export const adminGlobalRewardService = {
    async catalog(): Promise<GlobalRewardCatalog> {
        const response = await api.get(root);
        return response.data.data;
    },
    updateGame: (gameId: number, packages: Record<string, unknown>[]) => api.put(`${root}/${gameId}`, { packages }),
};
