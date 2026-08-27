export type ProviderRequestSnapshot = {
    method?: string;
    url?: string;
    headers?: Record<string, string | string[]>;
    payload?: Record<string, unknown>;
    raw_body?: string | null;
};

export type ProviderResponseSnapshot = {
    http_status?: number | null;
    reason?: string | null;
    effective_uri?: string | null;
    headers?: Record<string, string | string[]>;
    body?: unknown;
    raw_body?: string | null;
    transport_error?: {
        class?: string | null;
        message?: string | null;
    } | null;
};

export type ProviderExchange = {
    request?: ProviderRequestSnapshot;
    response?: ProviderResponseSnapshot;
    status?: string | null;
    message?: string | null;
    attempt?: number;
    recorded_at?: string | null;
};

export type ProviderItemRow = {
    unit?: number | null;
    request_id?: string | null;
    reference?: string | null;
    status?: string | null;
    current_step?: string | null;
    message?: string | null;
    http_status?: number | null;
    provider_code?: string | number | null;
    provider_status?: string | null;
    provider_topup_id?: string | null;
    envelope_status?: string | null;
    check_attempts?: number;
    submitted_at?: string | null;
    last_checked_at?: string | null;
    submission?: ProviderExchange | null;
    last_status_check?: ProviderExchange | null;
    last_error?: ProviderExchange | null;
};

export type RecipientRow = {
    position: number;
    data: Record<string, unknown>;
    quantity: number;
    status: string;
    provider_reference?: string | null;
    failure_reason?: string | null;
    provider_items?: ProviderItemRow[];
};

export type OrderRow = {
    id: number;
    code: string;
    email: string;
    user_id?: number | null;
    game?: string | null;
    server?: string | null;
    game_account?: string | null;
    game_character?: string | null;
    package_name: string;
    quantity: number;
    purchase_mode?: string | null;
    provider?: {
        id: number;
        name: string;
        slug: string;
    } | null;
    checkout_fields?: Record<string, unknown>;
    total_amount: number | string;
    payment_method?: string | null;
    payment_status: string;
    order_status: string;
    can_reorder: boolean;
    can_sync_provider: boolean;
    provider_reference?: string | null;
    failure_reason?: string | null;
    paid_at?: string | null;
    created_at: string;
    recipients?: RecipientRow[];
};

export type OrderAction = 'detail' | 'mark_paid' | 'process' | 'reorder' | 'sync_provider' | 'complete' | 'fail' | 'cancel';

export type ActionOption = {
    action: OrderAction;
    label: string;
    tone?: 'primary' | 'danger' | 'neutral';
};
