import { WalletType } from './wallet.type';

export interface UserType {
    id: number;
    username: string;
    email: string | null;
    phone: string | null;
    full_name: string | null;
    avatar: string | null;
    email_verified_at: string | null;
    role: 'user' | 'admin' | 'ctv';
    status: number;
    last_login_at: string | null;
    last_login_ip: string | null;
    referral_code: string | null;
    referred_by: string | null;
    created_at: string;
    updated_at: string;
    wallet: WalletType;
    site?: {
        id: number;
        name: string;
        slug: string;
        is_main: boolean;
    };
    capabilities?: {
        platform_admin: boolean;
        tenant_admin: boolean;
        multi_site: boolean;
    };
}
