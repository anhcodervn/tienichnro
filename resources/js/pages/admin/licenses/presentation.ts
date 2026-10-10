import type { LicenseKey, LicensePlan, LicenseProduct } from '@/services/admin-license.service';

export function licenseCatalogRows<T extends { name: string; is_active: boolean; product_code?: string; product_id?: number }>(
    rows: T[],
    filters: { search: string; status: string; product_id: string },
): T[] {
    const search = filters.search.trim().toLocaleLowerCase('vi-VN');
    return rows.filter(
        (row) =>
            (!search || `${row.name} ${row.product_code || ''}`.toLocaleLowerCase('vi-VN').includes(search)) &&
            (!filters.status || row.is_active === (filters.status === 'active')) &&
            (!filters.product_id || row.product_id === Number(filters.product_id)),
    );
}

export function availableLicensePlans(products: LicenseProduct[], plans: LicensePlan[]): LicensePlan[] {
    const activeProducts = new Set(products.filter((product) => product.is_active).map((product) => product.id));
    return plans.filter((plan) => plan.is_active && activeProducts.has(plan.product_id));
}

export function licenseActions(license: LicenseKey): string[] {
    if (license.status === 'revoked') return [];
    const actions = license.status === 'suspended' ? ['resume', 'extend'] : ['extend', 'suspend'];
    if (license.current_device_uuid) actions.push('revoke-session', 'reset-device');
    if (license.devices?.some((device) => device.device_uuid !== license.current_device_uuid)) actions.push('transfer');
    return [...actions, 'revoke'];
}

export function licenseExpiryLabel(license: Pick<LicenseKey, 'activated_at' | 'expires_at'>): string {
    if (!license.activated_at) return 'Chưa kích hoạt';
    if (!license.expires_at) return 'Vĩnh viễn';
    return new Date(license.expires_at).toLocaleDateString('vi-VN');
}

export function effectiveSessionStatus(session: { status: string; lease_expires_at: string }, timestamp = Date.now()): string {
    return session.status === 'active' && new Date(session.lease_expires_at).getTime() <= timestamp ? 'expired' : session.status;
}

export function canCloseLicenseDialog(saving: boolean, modal: string | null, keysSaved: boolean): boolean {
    return !saving && (modal !== 'issued' || keysSaved);
}
