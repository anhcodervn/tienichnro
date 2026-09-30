<?php

namespace App\Features\Reporting\Services;

use App\Models\GameServiceOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class GameServiceReportService
{
    /** @return array<string, mixed> */
    public function report(?string $fromDate = null, ?string $toDate = null): array
    {
        [$from, $to] = $this->period($fromDate, $toDate);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1,
                'timezone' => config('app.timezone'),
            ],
            'orders' => $this->orderSummary($from, $to),
            'funds' => $this->fundSummary($from, $to),
            'financials' => $this->financialSummary($from, $to),
            'breakdowns' => [
                'games' => $this->breakdown($from, $to, 'game_name'),
                'services' => $this->breakdown($from, $to, 'service_name'),
            ],
            'recent_completed_orders' => $this->recentCompletedOrders($from, $to),
            'criteria' => 'Số lượng trạng thái và tiền đang treo tính theo ngày tạo đơn. Doanh thu, chi phí CTV, thuế và lợi nhuận chỉ tính đơn đã được admin duyệt hoàn thành theo ngày hoàn thành.',
        ];
    }

    /** @return array<string, int|float> */
    private function orderSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $summary = $this->createdOrders($from, $to)
            ->toBase()
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders")
            ->selectRaw("SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders")
            ->selectRaw("SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) as review_orders")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_orders")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders")
            ->first();
        $total = (int) ($summary?->total_orders ?? 0);
        $completed = (int) ($summary?->completed_orders ?? 0);

        return [
            'total_orders' => $total,
            'pending_orders' => (int) ($summary?->pending_orders ?? 0),
            'processing_orders' => (int) ($summary?->processing_orders ?? 0),
            'review_orders' => (int) ($summary?->review_orders ?? 0),
            'reported_completion_orders' => (int) ($summary?->review_orders ?? 0) + $completed,
            'completed_orders' => $completed,
            'failed_orders' => (int) ($summary?->failed_orders ?? 0),
            'cancelled_orders' => (int) ($summary?->cancelled_orders ?? 0),
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
        ];
    }

    /** @return array<string, int> */
    private function fundSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $summary = $this->createdOrders($from, $to)
            ->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('processing', 'review') AND collaborator_held_at IS NOT NULL AND collaborator_refunded_at IS NULL THEN collaborator_total_cost ELSE 0 END), 0) as working_hold")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'completed' AND collaborator_settled_at IS NULL AND collaborator_refunded_at IS NULL THEN collaborator_total_cost ELSE 0 END), 0) as pending_settlement")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'completed' AND collaborator_settled_at IS NOT NULL AND collaborator_refunded_at IS NULL THEN collaborator_settlement_amount ELSE 0 END), 0) as settled")
            ->selectRaw('COALESCE(SUM(CASE WHEN collaborator_refunded_at IS NOT NULL THEN collaborator_total_cost ELSE 0 END), 0) as reversed')
            ->first();

        return [
            'working_hold' => (int) ($summary?->working_hold ?? 0),
            'pending_settlement' => (int) ($summary?->pending_settlement ?? 0),
            'total_unsettled' => (int) ($summary?->working_hold ?? 0) + (int) ($summary?->pending_settlement ?? 0),
            'settled' => (int) ($summary?->settled ?? 0),
            'reversed' => (int) ($summary?->reversed ?? 0),
        ];
    }

    /** @return array<string, int|float> */
    private function financialSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $summary = $this->completedOrders($from, $to)
            ->toBase()
            ->selectRaw('COUNT(*) as completed_orders')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(collaborator_total_cost), 0) as collaborator_cost')
            ->selectRaw('COALESCE(SUM(gross_profit), 0) as after_collaborator')
            ->selectRaw('COALESCE(SUM(estimated_tax), 0) as estimated_tax')
            ->selectRaw('COALESCE(SUM(net_profit), 0) as net_profit')
            ->selectRaw('COUNT(CASE WHEN collaborator_total_cost IS NULL OR net_profit IS NULL THEN 1 END) as legacy_orders')
            ->first();
        $revenue = (int) ($summary?->revenue ?? 0);
        $netProfit = (int) ($summary?->net_profit ?? 0);

        return [
            'completed_orders' => (int) ($summary?->completed_orders ?? 0),
            'revenue' => $revenue,
            'collaborator_cost' => (int) ($summary?->collaborator_cost ?? 0),
            'after_collaborator' => (int) ($summary?->after_collaborator ?? 0),
            'estimated_tax' => (int) ($summary?->estimated_tax ?? 0),
            'net_profit' => $netProfit,
            'net_margin_percent' => $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : 0.0,
            'legacy_orders' => (int) ($summary?->legacy_orders ?? 0),
        ];
    }

    /** @return array<int, array<string, int|string>> */
    private function breakdown(CarbonImmutable $from, CarbonImmutable $to, string $column): array
    {
        return $this->completedOrders($from, $to)
            ->select($column)
            ->selectRaw('COUNT(*) as completed_orders')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(collaborator_total_cost), 0) as collaborator_cost')
            ->selectRaw('COALESCE(SUM(gross_profit), 0) as after_collaborator')
            ->selectRaw('COALESCE(SUM(estimated_tax), 0) as estimated_tax')
            ->selectRaw('COALESCE(SUM(net_profit), 0) as net_profit')
            ->groupBy($column)
            ->orderByDesc('revenue')
            ->limit(20)
            ->get()
            ->map(fn (GameServiceOrder $row): array => [
                'name' => (string) $row->getAttribute($column),
                'completed_orders' => (int) $row->completed_orders,
                'revenue' => (int) $row->revenue,
                'collaborator_cost' => (int) $row->collaborator_cost,
                'after_collaborator' => (int) $row->after_collaborator,
                'estimated_tax' => (int) $row->estimated_tax,
                'net_profit' => (int) $row->net_profit,
            ])->all();
    }

    /** @return array<int, array<string, int|string|null>> */
    private function recentCompletedOrders(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->completedOrders($from, $to)
            ->latest('completed_at')
            ->limit(10)
            ->get([
                'code', 'game_name', 'service_name', 'package_name', 'total_amount', 'collaborator_total_cost',
                'gross_profit', 'estimated_tax', 'net_profit', 'collaborator_settled_at', 'completed_at',
            ])
            ->map(fn (GameServiceOrder $order): array => [
                'code' => $order->code,
                'game' => $order->game_name,
                'service' => $order->service_name,
                'package' => $order->package_name,
                'revenue' => (int) $order->total_amount,
                'collaborator_cost' => (int) $order->collaborator_total_cost,
                'after_collaborator' => (int) $order->gross_profit,
                'estimated_tax' => (int) $order->estimated_tax,
                'net_profit' => (int) $order->net_profit,
                'settled_at' => $order->collaborator_settled_at?->toISOString(),
                'completed_at' => $order->completed_at?->toISOString(),
            ])->all();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
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

    private function createdOrders(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return GameServiceOrder::query()->whereBetween('created_at', [$from, $to]);
    }

    private function completedOrders(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return GameServiceOrder::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to]);
    }
}
