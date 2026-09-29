<?php

namespace App\Features\Admin\GameService\Actions;

use App\Features\Topup\Services\OrderProfitCalculatorService;
use App\Features\Topup\Services\TaxConfigurationService;
use App\Models\GameServiceOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BackfillGameServiceOrderSettlementsAction
{
    /** @var array<int, string> */
    private const SETTLEMENT_COLUMNS = [
        'collaborator_unit_cost',
        'collaborator_total_cost',
        'gross_profit',
        'tax_enabled',
        'tax_calculation_type',
        'vat_rate',
        'pit_rate',
        'estimated_vat',
        'estimated_pit',
        'estimated_tax',
        'net_profit',
        'profit_margin',
    ];

    public function __construct(
        private readonly TaxConfigurationService $taxConfigurationService,
        private readonly OrderProfitCalculatorService $orderProfitCalculator,
    ) {}

    /**
     * @return array{eligible: int, updated: int, missing_price: int}
     */
    public function handle(bool $apply = false): array
    {
        $fullyMissing = $this->fullyMissingQuery();
        $eligible = (clone $fullyMissing)->whereHas('price')->count();
        $missingPrice = (clone $fullyMissing)->whereDoesntHave('price')->count();
        $updated = 0;

        if (! $apply || $eligible === 0) {
            return compact('eligible', 'updated', 'missingPrice');
        }

        $taxConfiguration = $this->taxConfigurationService->current();

        $this->fullyMissingQuery()
            ->whereHas('price')
            ->select('id')
            ->chunkById(100, function ($orders) use ($taxConfiguration, &$updated): void {
                foreach ($orders as $order) {
                    DB::transaction(function () use ($order, $taxConfiguration, &$updated): void {
                        $lockedOrder = GameServiceOrder::query()->with('price')->lockForUpdate()->find($order->id);

                        if (! $lockedOrder instanceof GameServiceOrder || ! $lockedOrder->price || ! $this->isFullyMissing($lockedOrder)) {
                            return;
                        }

                        $collaboratorUnitCost = $lockedOrder->price->collaborator_price;
                        $collaboratorTotalCost = $collaboratorUnitCost * $lockedOrder->quantity;
                        $profit = $this->orderProfitCalculator->calculate(
                            costPrice: $collaboratorTotalCost,
                            salePrice: $lockedOrder->total_amount,
                            vatRate: $taxConfiguration['vat_rate'],
                            pitRate: $taxConfiguration['pit_rate'],
                            taxEnabled: $taxConfiguration['enabled'],
                            calculationType: $taxConfiguration['calculation_type'],
                        );

                        $lockedOrder->forceFill([
                            'collaborator_unit_cost' => $collaboratorUnitCost,
                            'collaborator_total_cost' => $collaboratorTotalCost,
                            'gross_profit' => $profit['gross_profit'],
                            'tax_enabled' => $taxConfiguration['enabled'],
                            'tax_calculation_type' => $taxConfiguration['calculation_type']->value,
                            'vat_rate' => $taxConfiguration['vat_rate'],
                            'pit_rate' => $taxConfiguration['pit_rate'],
                            'estimated_vat' => $profit['estimated_vat'],
                            'estimated_pit' => $profit['estimated_pit'],
                            'estimated_tax' => $profit['estimated_tax'],
                            'net_profit' => $profit['net_profit'],
                            'profit_margin' => $profit['profit_margin'],
                        ])->saveQuietly();
                        $updated++;
                    }, 3);
                }
            });

        return compact('eligible', 'updated', 'missingPrice');
    }

    private function fullyMissingQuery(): Builder
    {
        return GameServiceOrder::query()->where(function (Builder $query): void {
            foreach (self::SETTLEMENT_COLUMNS as $column) {
                $query->whereNull($column);
            }
        });
    }

    private function isFullyMissing(GameServiceOrder $order): bool
    {
        foreach (self::SETTLEMENT_COLUMNS as $column) {
            if ($order->getAttribute($column) !== null) {
                return false;
            }
        }

        return true;
    }
}
