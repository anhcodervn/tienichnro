<?php

namespace App\Features\Reporting\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Topup\Services\OrderProfitCalculatorService;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopupReportService
{
    public function __construct(private readonly OrderProfitCalculatorService $profitCalculator) {}

    /** @return array<string, mixed> */
    public function report(?string $fromDate = null, ?string $toDate = null): array
    {
        [$from, $to] = $this->period($fromDate, $toDate);
        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $previousTo = $from->subDay()->endOfDay();
        $previousFrom = $previousTo->subDays($days - 1)->startOfDay();
        $current = $this->successfulSummary($from, $to);
        $previous = $this->successfulSummary($previousFrom, $previousTo);
        $statusOverview = $this->statusOverview($from, $to);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
                'previous_from' => $previousFrom->toDateString(),
                'previous_to' => $previousTo->toDateString(),
                'timezone' => config('app.timezone'),
            ],
            'summary' => [
                ...$current,
                'completion_rate' => $statusOverview['created_orders'] > 0
                    ? round(($statusOverview['completed_orders'] / $statusOverview['created_orders']) * 100, 1)
                    : 0.0,
            ],
            'growth' => [
                'revenue' => $this->growth($current['revenue'], $previous['revenue']),
                'provider_cost' => $this->growth($current['provider_cost'], $previous['provider_cost']),
                'gross_profit' => $this->growth($current['gross_profit'], $previous['gross_profit']),
                'estimated_tax' => $this->growth($current['estimated_tax'], $previous['estimated_tax']),
                'net_profit' => $this->growth($current['net_profit'], $previous['net_profit']),
                'net_margin_percent' => $this->growth($current['net_margin_percent'], $previous['net_margin_percent']),
                'gross_margin_percent' => $this->growth($current['gross_margin_percent'], $previous['gross_margin_percent']),
                'successful_orders' => $this->growth($current['successful_orders'], $previous['successful_orders']),
                'successful_units' => $this->growth($current['successful_units'], $previous['successful_units']),
                'average_order_value' => $this->growth($current['average_order_value'], $previous['average_order_value']),
            ],
            'status_overview' => $statusOverview,
            'trend' => $this->dailyTrend($from, $to),
            'breakdowns' => [
                'games' => $this->gameBreakdown($from, $to),
                'providers' => $this->providerBreakdown($from, $to),
                'packages' => $this->packageBreakdown($from, $to),
            ],
            'recent_successful_orders' => $this->recentSuccessfulOrders($from, $to),
            'criteria' => 'Doanh thu chỉ tính đơn đã thanh toán và hoàn thành. Thuế và lợi nhuận dùng snapshot tại lúc tạo đơn, không tính lại theo cấu hình hiện tại.',
        ];
    }

    /** @return array{successful_orders:int,successful_units:int,revenue:int,average_order_value:int,provider_cost:int,gross_profit:int,gross_margin_percent:float,priced_orders:int,unpriced_orders:int,unpriced_revenue:int} */
    public function successfulSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $summary = $this->successfulOrders($from, $to)
            ->selectRaw('COUNT(*) as successful_orders')
            ->selectRaw('COALESCE(SUM(quantity), 0) as successful_units')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN provider_total_cost IS NOT NULL THEN provider_total_cost ELSE 0 END), 0) as provider_cost')
            ->selectRaw('COALESCE(SUM(CASE WHEN gross_profit IS NOT NULL THEN gross_profit ELSE 0 END), 0) as gross_profit')
            ->selectRaw('COALESCE(SUM(CASE WHEN gross_profit IS NOT NULL THEN total_amount ELSE 0 END), 0) as priced_revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN gross_profit IS NOT NULL THEN 1 ELSE 0 END), 0) as priced_orders')
            ->selectRaw('COALESCE(SUM(CASE WHEN gross_profit IS NULL THEN 1 ELSE 0 END), 0) as unpriced_orders')
            ->selectRaw('COALESCE(SUM(CASE WHEN gross_profit IS NULL THEN total_amount ELSE 0 END), 0) as unpriced_revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN tax_enabled IS NOT NULL THEN estimated_vat ELSE 0 END), 0) as estimated_vat')
            ->selectRaw('COALESCE(SUM(CASE WHEN tax_enabled IS NOT NULL THEN estimated_pit ELSE 0 END), 0) as estimated_pit')
            ->selectRaw('COALESCE(SUM(CASE WHEN tax_enabled IS NOT NULL THEN estimated_tax ELSE 0 END), 0) as estimated_tax')
            ->selectRaw('COALESCE(SUM(CASE WHEN tax_enabled IS NOT NULL THEN payment_fee ELSE 0 END), 0) as payment_fee')
            ->selectRaw('COALESCE(SUM(CASE WHEN tax_enabled IS NOT NULL THEN other_cost ELSE 0 END), 0) as other_cost')
            ->selectRaw('COALESCE(SUM(CASE WHEN net_profit IS NOT NULL THEN net_profit ELSE 0 END), 0) as net_profit')
            ->selectRaw('COALESCE(SUM(CASE WHEN net_profit IS NOT NULL THEN total_amount ELSE 0 END), 0) as net_priced_revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN tax_enabled IS NOT NULL THEN 1 ELSE 0 END), 0) as tax_snapshot_orders')
            ->selectRaw('COALESCE(SUM(CASE WHEN tax_enabled IS NULL THEN 1 ELSE 0 END), 0) as legacy_tax_orders')
            ->selectRaw('COALESCE(SUM(CASE WHEN tax_enabled IS NULL THEN total_amount ELSE 0 END), 0) as legacy_tax_revenue')
            ->first();
        $successfulOrders = (int) ($summary?->successful_orders ?? 0);
        $revenue = (int) ($summary?->revenue ?? 0);
        $pricedRevenue = (int) ($summary?->priced_revenue ?? 0);
        $grossProfit = (int) ($summary?->gross_profit ?? 0);
        $netProfit = (int) ($summary?->net_profit ?? 0);
        $netPricedRevenue = (int) ($summary?->net_priced_revenue ?? 0);

        return [
            'successful_orders' => $successfulOrders,
            'successful_units' => (int) ($summary?->successful_units ?? 0),
            'revenue' => $revenue,
            'total_revenue' => $revenue,
            'average_order_value' => $successfulOrders > 0 ? (int) round($revenue / $successfulOrders) : 0,
            'provider_cost' => (int) ($summary?->provider_cost ?? 0),
            'total_cost' => (int) ($summary?->provider_cost ?? 0),
            'gross_profit' => $grossProfit,
            'total_gross_profit' => $grossProfit,
            'gross_margin_percent' => $pricedRevenue > 0 ? round(($grossProfit / $pricedRevenue) * 100, 1) : 0.0,
            'estimated_vat' => (int) ($summary?->estimated_vat ?? 0),
            'total_estimated_vat' => (int) ($summary?->estimated_vat ?? 0),
            'estimated_pit' => (int) ($summary?->estimated_pit ?? 0),
            'total_estimated_pit' => (int) ($summary?->estimated_pit ?? 0),
            'estimated_tax' => (int) ($summary?->estimated_tax ?? 0),
            'total_estimated_tax' => (int) ($summary?->estimated_tax ?? 0),
            'payment_fee' => (int) ($summary?->payment_fee ?? 0),
            'total_payment_fee' => (int) ($summary?->payment_fee ?? 0),
            'other_cost' => (int) ($summary?->other_cost ?? 0),
            'total_other_cost' => (int) ($summary?->other_cost ?? 0),
            'net_profit' => $netProfit,
            'total_net_profit' => $netProfit,
            'net_margin_percent' => $this->profitCalculator->profitMargin($netProfit, $netPricedRevenue) ?? 0.0,
            'tax_snapshot_orders' => (int) ($summary?->tax_snapshot_orders ?? 0),
            'legacy_tax_orders' => (int) ($summary?->legacy_tax_orders ?? 0),
            'legacy_tax_revenue' => (int) ($summary?->legacy_tax_revenue ?? 0),
            'priced_orders' => (int) ($summary?->priced_orders ?? 0),
            'unpriced_orders' => (int) ($summary?->unpriced_orders ?? 0),
            'unpriced_revenue' => (int) ($summary?->unpriced_revenue ?? 0),
        ];
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} */
    private function period(?string $fromDate, ?string $toDate): array
    {
        $timezone = (string) config('app.timezone');
        $to = $toDate === null
            ? CarbonImmutable::now($timezone)->endOfDay()
            : CarbonImmutable::parse($toDate, $timezone)->endOfDay();
        $from = $fromDate === null
            ? $to->subDays(29)->startOfDay()
            : CarbonImmutable::parse($fromDate, $timezone)->startOfDay();

        return [$from, $to];
    }

    private function successfulOrders(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('order_status', OrderStatus::Completed)
            ->whereBetween('completed_at', [$from, $to]);
    }

    /** @return array<string, int> */
    private function statusOverview(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $summary = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) as created_orders')
            ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as completed_orders', [OrderStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as processing_orders', [OrderStatus::Processing->value])
            ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as failed_orders', [OrderStatus::Failed->value])
            ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as pending_orders', [OrderStatus::Pending->value])
            ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as cancelled_orders', [OrderStatus::Cancelled->value])
            ->first();

        return [
            'created_orders' => (int) ($summary?->created_orders ?? 0),
            'completed_orders' => (int) ($summary?->completed_orders ?? 0),
            'processing_orders' => (int) ($summary?->processing_orders ?? 0),
            'failed_orders' => (int) ($summary?->failed_orders ?? 0),
            'pending_orders' => (int) ($summary?->pending_orders ?? 0),
            'cancelled_orders' => (int) ($summary?->cancelled_orders ?? 0),
        ];
    }

    /** @return array<int, array{date:string,revenue:int,provider_cost:int,gross_profit:int,successful_orders:int,successful_units:int}> */
    private function dailyTrend(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dateExpression = DB::getDriverName() === 'pgsql'
            ? "TO_CHAR(completed_at, 'YYYY-MM-DD')"
            : 'DATE(completed_at)';
        $rows = $this->successfulOrders($from, $to)
            ->selectRaw("{$dateExpression} as report_date")
            ->selectRaw('COUNT(*) as successful_orders')
            ->selectRaw('COALESCE(SUM(quantity), 0) as successful_units')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN provider_total_cost IS NOT NULL THEN provider_total_cost ELSE 0 END), 0) as provider_cost')
            ->selectRaw('COALESCE(SUM(CASE WHEN gross_profit IS NOT NULL THEN gross_profit ELSE 0 END), 0) as gross_profit')
            ->selectRaw('COALESCE(SUM(CASE WHEN tax_enabled IS NOT NULL THEN estimated_tax ELSE 0 END), 0) as estimated_tax')
            ->selectRaw('COALESCE(SUM(CASE WHEN net_profit IS NOT NULL THEN net_profit ELSE 0 END), 0) as net_profit')
            ->groupByRaw($dateExpression)
            ->orderBy('report_date')
            ->get()
            ->keyBy('report_date');
        $trend = [];

        for ($date = $from->startOfDay(); $date->lte($to); $date = $date->addDay()) {
            $key = $date->toDateString();
            $row = $rows->get($key);
            $trend[] = [
                'date' => $key,
                'revenue' => (int) ($row?->revenue ?? 0),
                'provider_cost' => (int) ($row?->provider_cost ?? 0),
                'gross_profit' => (int) ($row?->gross_profit ?? 0),
                'estimated_tax' => (int) ($row?->estimated_tax ?? 0),
                'net_profit' => (int) ($row?->net_profit ?? 0),
                'successful_orders' => (int) ($row?->successful_orders ?? 0),
                'successful_units' => (int) ($row?->successful_units ?? 0),
            ];
        }

        return $trend;
    }

    /** @return array<int, array<string, int|string|null>> */
    private function gameBreakdown(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->successfulOrders($from, $to)
            ->join('games', 'games.id', '=', 'orders.game_id')
            ->select(['games.id', 'games.name'])
            ->selectRaw('COUNT(*) as successful_orders, COALESCE(SUM(orders.quantity), 0) as successful_units, COALESCE(SUM(orders.total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.provider_total_cost IS NOT NULL THEN orders.provider_total_cost ELSE 0 END), 0) as provider_cost, COALESCE(SUM(CASE WHEN orders.gross_profit IS NOT NULL THEN orders.gross_profit ELSE 0 END), 0) as gross_profit')
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.tax_enabled IS NOT NULL THEN orders.estimated_tax ELSE 0 END), 0) as estimated_tax, COALESCE(SUM(CASE WHEN orders.net_profit IS NOT NULL THEN orders.net_profit ELSE 0 END), 0) as net_profit')
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.gross_profit IS NULL THEN 1 ELSE 0 END), 0) as unpriced_orders')
            ->groupBy('games.id', 'games.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn (Order $row): array => $this->breakdownRow($row->id, $row->name, $row))
            ->all();
    }

    /** @return array<int, array<string, int|string|null>> */
    private function providerBreakdown(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->successfulOrders($from, $to)
            ->leftJoin('topup_providers', 'topup_providers.id', '=', 'orders.topup_provider_id')
            ->select(['topup_providers.id', 'topup_providers.name'])
            ->selectRaw('COUNT(*) as successful_orders, COALESCE(SUM(orders.quantity), 0) as successful_units, COALESCE(SUM(orders.total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.provider_total_cost IS NOT NULL THEN orders.provider_total_cost ELSE 0 END), 0) as provider_cost, COALESCE(SUM(CASE WHEN orders.gross_profit IS NOT NULL THEN orders.gross_profit ELSE 0 END), 0) as gross_profit')
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.tax_enabled IS NOT NULL THEN orders.estimated_tax ELSE 0 END), 0) as estimated_tax, COALESCE(SUM(CASE WHEN orders.net_profit IS NOT NULL THEN orders.net_profit ELSE 0 END), 0) as net_profit')
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.gross_profit IS NULL THEN 1 ELSE 0 END), 0) as unpriced_orders')
            ->groupBy('topup_providers.id', 'topup_providers.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn (Order $row): array => $this->breakdownRow($row->id, $row->name ?? 'Không xác định', $row))
            ->all();
    }

    /** @return array<int, array<string, int|string|null>> */
    private function packageBreakdown(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->successfulOrders($from, $to)
            ->select('package_name')
            ->selectRaw('COUNT(*) as successful_orders, COALESCE(SUM(quantity), 0) as successful_units, COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN provider_total_cost IS NOT NULL THEN provider_total_cost ELSE 0 END), 0) as provider_cost, COALESCE(SUM(CASE WHEN gross_profit IS NOT NULL THEN gross_profit ELSE 0 END), 0) as gross_profit')
            ->selectRaw('COALESCE(SUM(CASE WHEN tax_enabled IS NOT NULL THEN estimated_tax ELSE 0 END), 0) as estimated_tax, COALESCE(SUM(CASE WHEN net_profit IS NOT NULL THEN net_profit ELSE 0 END), 0) as net_profit')
            ->selectRaw('COALESCE(SUM(CASE WHEN gross_profit IS NULL THEN 1 ELSE 0 END), 0) as unpriced_orders')
            ->groupBy('package_name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn (Order $row): array => $this->breakdownRow(null, $row->package_name, $row))
            ->all();
    }

    /** @return array<int, array<string, int|string|null>> */
    private function recentSuccessfulOrders(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->successfulOrders($from, $to)
            ->with(['game:id,name', 'provider:id,name'])
            ->latest('completed_at')
            ->limit(10)
            ->get([
                'id', 'code', 'game_id', 'topup_provider_id', 'package_name', 'quantity', 'total_amount',
                'provider_total_cost', 'gross_profit', 'tax_enabled', 'estimated_tax', 'net_profit', 'completed_at',
            ])
            ->map(fn (Order $order): array => [
                'code' => $order->code,
                'game' => $order->game?->name,
                'provider' => $order->provider?->name,
                'package' => $order->package_name,
                'successful_units' => $order->quantity,
                'revenue' => (int) $order->total_amount,
                'provider_cost' => $order->provider_total_cost === null ? null : (int) $order->provider_total_cost,
                'gross_profit' => $order->gross_profit === null ? null : (int) $order->gross_profit,
                'estimated_tax' => $order->tax_enabled === null ? null : (int) $order->estimated_tax,
                'net_profit' => $order->net_profit === null ? null : (int) $order->net_profit,
                'completed_at' => $order->completed_at?->toISOString(),
            ])->all();
    }

    /** @return array{id:int|null,name:string,successful_orders:int,successful_units:int,revenue:int,provider_cost:int,gross_profit:int,unpriced_orders:int} */
    private function breakdownRow(mixed $id, string $name, Order $row): array
    {
        return [
            'id' => $id === null ? null : (int) $id,
            'name' => $name,
            'successful_orders' => (int) $row->successful_orders,
            'successful_units' => (int) $row->successful_units,
            'revenue' => (int) $row->revenue,
            'provider_cost' => (int) $row->provider_cost,
            'gross_profit' => (int) $row->gross_profit,
            'estimated_tax' => (int) $row->estimated_tax,
            'net_profit' => (int) $row->net_profit,
            'unpriced_orders' => (int) $row->unpriced_orders,
        ];
    }

    /** @return array{current:int|float,previous:int|float,absolute_change:int|float,percentage_change:float|null} */
    private function growth(int|float $current, int|float $previous): array
    {
        return [
            'current' => $current,
            'previous' => $previous,
            'absolute_change' => $current - $previous,
            'percentage_change' => $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null,
        ];
    }
}
