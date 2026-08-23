export type RecipientRow = {
    position: number;
    data: Record<string, unknown>;
    quantity: number;
    status: string;
    provider_reference?: string | null;
    failure_reason?: string | null;
    provider_items?: Array<{
        unit?: number | null;
        status?: string | null;
        message?: string | null;
        http_status?: number | null;
        provider_code?: string | number | null;
        check_attempts?: number;
        last_checked_at?: string | null;
    }>;
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
    checkout_fields?: Record<string, unknown>;
    total_amount: number | string;
    payment_method?: string | null;
    payment_status: string;
    order_status: string;
    can_reorder: boolean;
    provider_reference?: string | null;
    failure_reason?: string | null;
    paid_at?: string | null;
    created_at: string;
    recipients?: RecipientRow[];
};

export type OrderAction = 'detail' | 'mark_paid' | 'process' | 'reorder' | 'complete' | 'fail' | 'cancel';

export type ActionOption = {
    action: OrderAction;
    label: string;
    tone?: 'primary' | 'danger' | 'neutral';
};
