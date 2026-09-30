const storageKey = 'napcarot.game-service-secondary-auth';

export type GameServiceSecondaryGrant = {
    token: string;
    expires_at: string;
};

export const getGameServiceSecondaryGrant = (): GameServiceSecondaryGrant | null => {
    try {
        const rawGrant = window.sessionStorage.getItem(storageKey);
        if (!rawGrant) return null;

        const grant = JSON.parse(rawGrant) as GameServiceSecondaryGrant;
        if (!grant.token || !grant.expires_at || new Date(grant.expires_at).getTime() <= Date.now()) {
            window.sessionStorage.removeItem(storageKey);
            return null;
        }

        return grant;
    } catch {
        return null;
    }
};

export const storeGameServiceSecondaryGrant = (grant: GameServiceSecondaryGrant): void => {
    window.sessionStorage.setItem(storageKey, JSON.stringify(grant));
};

export const clearGameServiceSecondaryGrant = (): void => {
    window.sessionStorage.removeItem(storageKey);
};

export const getGameServiceSecondaryToken = (): string | null => getGameServiceSecondaryGrant()?.token ?? null;
