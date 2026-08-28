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
        average_order_value: number;
        completion_rate: number;
        provider_cost: number;
        gross_profit: number;
        gross_margin_percent: number;
        priced_orders: number;
        unpriced_orders: number;
        unpriced_revenue: number;
    };
    growth: {
        revenue: GrowthMetric;
        provider_cost: GrowthMetric;
        gross_profit: GrowthMetric;
        gross_margin_percent: GrowthMetric;
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
        completed_at: string | null;
    }>;
    criteria: string;
};

export const adminReportingService = {
    topup: (params: { from?: string; to?: string }) =>
        axios.get<{ status: true; data: AdminTopupReport }>('/api/admin-api/reports/topup', { params }),
};
