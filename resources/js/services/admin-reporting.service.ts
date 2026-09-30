import axios from '@/config/axios';

export type GrowthMetric = {
    current: number;
    previous: number;
    absolute_change: number;
    percentage_change: number | null;
};

export type ReportBreakdown = {
    id: number | null;
    name: string;
    successful_orders: number;
    successful_units: number;
    revenue: number;
    provider_cost: number;
    gross_profit: number;
    estimated_tax: number;
    net_profit: number;
    unpriced_orders: number;
};

export type AdminTopupReport = {
    period: {
        from: string;
        to: string;
        days: number;
        previous_from: string;
        previous_to: string;
        timezone: string;
    };
    summary: {
        successful_orders: number;
        successful_units: number;
        revenue: number;
        total_revenue: number;
        average_order_value: number;
        completion_rate: number;
        provider_cost: number;
        total_cost: number;
        gross_profit: number;
        total_gross_profit: number;
        gross_margin_percent: number;
        estimated_vat: number;
        total_estimated_vat: number;
        estimated_pit: number;
        total_estimated_pit: number;
        estimated_tax: number;
        total_estimated_tax: number;
        payment_fee: number;
        total_payment_fee: number;
        other_cost: number;
        total_other_cost: number;
        net_profit: number;
        total_net_profit: number;
        net_margin_percent: number;
        tax_snapshot_orders: number;
        legacy_tax_orders: number;
        legacy_tax_revenue: number;
        priced_orders: number;
        unpriced_orders: number;
        unpriced_revenue: number;
    };
    growth: {
        revenue: GrowthMetric;
        provider_cost: GrowthMetric;
        gross_profit: GrowthMetric;
        gross_margin_percent: GrowthMetric;
        estimated_tax: GrowthMetric;
        net_profit: GrowthMetric;
        net_margin_percent: GrowthMetric;
        successful_orders: GrowthMetric;
        successful_units: GrowthMetric;
        average_order_value: GrowthMetric;
    };
    status_overview: {
        created_orders: number;
        completed_orders: number;
        processing_orders: number;
        failed_orders: number;
        pending_orders: number;
        cancelled_orders: number;
    };
    trend: Array<{
        date: string;
        revenue: number;
        provider_cost: number;
        gross_profit: number;
        estimated_tax: number;
        net_profit: number;
        successful_orders: number;
        successful_units: number;
    }>;
    breakdowns: {
        games: ReportBreakdown[];
        providers: ReportBreakdown[];
        packages: ReportBreakdown[];
    };
    recent_successful_orders: Array<{
        code: string;
        game: string | null;
        provider: string | null;
        package: string;
        successful_units: number;
        revenue: number;
        provider_cost: number | null;
        gross_profit: number | null;
        estimated_tax: number | null;
        net_profit: number | null;
        completed_at: string | null;
    }>;
    criteria: string;
};

export type GameServiceReportBreakdown = {
    name: string;
    completed_orders: number;
    revenue: number;
    collaborator_cost: number;
    after_collaborator: number;
    estimated_tax: number;
    net_profit: number;
};

export type AdminGameServiceReport = {
    period: { from: string; to: string; days: number; timezone: string };
    orders: {
        total_orders: number;
        pending_orders: number;
        processing_orders: number;
        review_orders: number;
        reported_completion_orders: number;
        completed_orders: number;
        failed_orders: number;
        cancelled_orders: number;
        completion_rate: number;
    };
    funds: {
        working_hold: number;
        pending_settlement: number;
        total_unsettled: number;
        settled: number;
        reversed: number;
    };
    financials: {
        completed_orders: number;
        revenue: number;
        collaborator_cost: number;
        after_collaborator: number;
        estimated_tax: number;
        net_profit: number;
        net_margin_percent: number;
        legacy_orders: number;
    };
    breakdowns: { games: GameServiceReportBreakdown[]; services: GameServiceReportBreakdown[] };
    recent_completed_orders: Array<{
        code: string;
        game: string;
        service: string;
        package: string;
        revenue: number;
        collaborator_cost: number;
        after_collaborator: number;
        estimated_tax: number;
        net_profit: number;
        settled_at: string | null;
        completed_at: string | null;
    }>;
    criteria: string;
};

export const adminReportingService = {
    topup: (params: { from?: string; to?: string }) =>
        axios.get<{ status: true; data: AdminTopupReport }>('/api/admin-api/reports/topup', { params }),
    gameServices: (params: { from?: string; to?: string }) =>
        axios.get<{ status: true; data: AdminGameServiceReport }>('/api/admin-api/reports/game-services', { params }),
};
