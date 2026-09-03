export type RechargeConfigType = {
    id: number;
    provider: 'manual' | 'apibankvn_api';
    bank_name: string;
    account_name: string;
    account_number: string;
    qr_template: string;
    qr_url: string | null;
    transfer_prefix: string;
    transfer_content: string | null;
    preview_transfer_content: string;
    preview_qr_url: string | null;
    api_base_url: string | null;
    api_key: string | null;
    api_secret: string | null;
    webhook_secret: string | null;
    api_bank_id: number | null;
    api_ready: boolean;
    is_active: boolean;
    created_at: string | null;
    updated_at: string | null;
};

export type ApiBankVnBankAccountOption = {
    bank_id: number;
    bank_code: string;
    bank_name: string;
    bank_full_name: string | null;
    bank_short_name: string | null;
    bank_logo: string | null;
    bank_bg_color: string | null;
    account_name: string;
    account_number: string;
    username: string | null;
    status: string;
    last_sync_at: string | null;
};

export type DepositRequestItem = {
    id: number | string;
    code: string;
    created_at: string | null;
    method: {
        id: string;
        name: string;
    };
    amount: number;
    bonus_amount: number;
    bonus_percent: number;
    credited_amount: number;
    status: 'pending' | 'processing' | 'paid' | 'failed' | 'cancelled' | 'expired';
    content: string | null;
    account_number: string | null;
    bank_name: string | null;
    account_name: string | null;
    qr_url: string | null;
    confirmed_at: string | null;
    expires_at: string | null;
    can_confirm: boolean;
};

export type RechargeBonusTierType = {
    id: number;
    minimum_amount: number;
    bonus_basis_points: number;
    bonus_percent: number;
    is_active: boolean;
};

export type DepositRequestListResponse = {
    data: DepositRequestItem[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};
