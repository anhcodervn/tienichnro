import api from '@/config/axios';
import { storeGameServiceSecondaryGrant } from '@/utils/game-service-secondary-auth';

export type GameServiceSecondaryAuthStatus = {
    configured: boolean;
    unlocked: boolean;
    expires_in_minutes: number;
};

export type GameServiceSecondaryAuthGrant = {
    token: string;
    expires_at: string;
    expires_in_minutes: number;
};

const endpoint = '/api/client/affiliate/game-service-secondary-auth';

export const gameServiceSecondaryAuthService = {
    async status(): Promise<GameServiceSecondaryAuthStatus> {
        return (await api.get(endpoint)).data.data;
    },

    async unlock(password: string): Promise<GameServiceSecondaryAuthGrant> {
        const grant = (await api.post(endpoint, { password })).data.data as GameServiceSecondaryAuthGrant;
        storeGameServiceSecondaryGrant(grant);

        return grant;
    },
};
