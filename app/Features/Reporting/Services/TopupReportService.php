<?php

namespace App\Features\Reporting\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopupReportService
{
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
            'criteria' => 'Chỉ ghi nhận doanh thu và lượt nạp của đơn đã thanh toán, hoàn thành thành công trong kỳ.',
        ];
    }

    /** @return array{successful_orders:int,successful_units:int,revenue:int,average_order_value:int} */
    public function successfulSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $summary = $this->successfulOrders($from, $to)
            ->selectRaw('COUNT(*) as successful_orders')
            ->selectRaw('COALESCE(SUM(quantity), 0) as successful_units')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->first();
        $successfulOrders = (int) ($summary?->successful_orders ?? 0);
        $revenue = (int) ($summary?->revenue ?? 0);

        return [
            'successful_orders' => $successfulOrders,
            'successful_units' => (int) ($summary?->successful_units ?? 0),
            'revenue' => $revenue,
            'average_order_value' => $successfulOrders > 0 ? (int) round($revenue / $successfulOrders) : 0,
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

    /** @return array<int, array{date:string,revenue:int,successful_orders:int,successful_units:int}> */
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
            ->get(['id', 'code', 'game_id', 'topup_provider_id', 'package_name', 'quantity', 'total_amount', 'completed_at'])
            ->map(fn (Order $order): array => [
                'code' => $order->code,
                'game' => $order->game?->name,
                'provider' => $order->provider?->name,
                'package' => $order->package_name,
                'successful_units' => $order->quantity,
                'revenue' => (int) $order->total_amount,
                'completed_at' => $order->completed_at?->toISOString(),
            ])->all();
    }

    /** @return array{id:int|null,name:string,successful_orders:int,successful_units:int,revenue:int} */
    private function breakdownRow(mixed $id, string $name, Order $row): array
    {
        return [
            'id' => $id === null ? null : (int) $id,
            'name' => $name,
            'successful_orders' => (int) $row->successful_orders,
            'successful_units' => (int) $row->successful_units,
            'revenue' => (int) $row->revenue,
        ];
    }

    /** @return array{current:int,previous:int,absolute_change:int,percentage_change:float|null} */
    private function growth(int $current, int $previous): array
    {
        return [
            'current' => $current,
            'previous' => $previous,
            'absolute_change' => $current - $previous,
            'percentage_change' => $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null,
        ];
    }
}
