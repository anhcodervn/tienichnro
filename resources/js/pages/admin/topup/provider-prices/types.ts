export type ProviderStatus = 'unchecked' | 'success' | 'failed';

export type Provider = {
    id: number;
    name: string;
    slug: string;
    type: string;
    balance_status: ProviderStatus;
    balance_checked_at: string | null;
    balance_error_message: string | null;
    price_sync_status: ProviderStatus;
    price_synced_at: string | null;
    price_sync_error_code: string | null;
    price_sync_error_message: string | null;
    price_sync_latency_ms: number | null;
};

export type PriceRow = {
    id: number;
    scope: 'package' | 'global';
    game_name: string;
    name: string;
    denomination: number;
    provider_id: number | null;
    provider_name: string | null;
    provider_price: number;
    provider_prices: Record<string, number | null>;
    best_provider_id: number | null;
    price: number;
    original_price: number;
    profit: number;
    profit_percent: number;
};

export type ProviderSelection = {
    providerId: number;
    providerPrice: number;
    salePrice: number;
};

export const providerQuote = (row: PriceRow, providerId: number): number | null => {
    const value = row.provider_prices[String(providerId)];

    return value === null || value === undefined ? null : Number(value);
};

export const formatMoney = (value: number | null): string => (value === null ? '—' : `${new Intl.NumberFormat('vi-VN').format(Number(value) || 0)}đ`);
