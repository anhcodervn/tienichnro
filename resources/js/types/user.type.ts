export interface UserType {
    id: number;
    username: string;
    email: string | null;
    phone: string | null;
    full_name: string | null;
    avatar: string | null;
    email_verified_at: string | null;
    role: 'user' | 'admin';
    status: number;
    last_login_at: string | null;
    last_login_ip: string | null;
    created_at: string;
    updated_at: string;
    capabilities?: {
        platform_admin: boolean;
    };
}
