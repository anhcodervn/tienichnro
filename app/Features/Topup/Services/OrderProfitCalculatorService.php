<?php

namespace App\Features\Topup\Services;

use App\Enums\TaxCalculationType;
use InvalidArgumentException;
use LogicException;

class OrderProfitCalculatorService
{
    /**
     * @return array{gross_profit: int|null, estimated_vat: int, estimated_pit: int, estimated_tax: int, net_profit: int|null, profit_margin: float|null}
     */
    public function calculate(
        ?int $costPrice,
        int $salePrice,
        string $vatRate,
        string $pitRate,
        bool $taxEnabled,
        TaxCalculationType $calculationType = TaxCalculationType::Revenue,
        int $paymentFee = 0,
        int $otherCost = 0,
    ): array {
        if ($salePrice < 0 || ($costPrice !== null && $costPrice < 0) || $paymentFee < 0 || $otherCost < 0) {
            throw new InvalidArgumentException('Financial amounts must not be negative.');
        }

        if ($calculationType !== TaxCalculationType::Revenue) {
            throw new LogicException('Profit-based estimated tax calculation is not supported yet.');
        }

        $estimatedVat = $taxEnabled ? $this->taxOnRevenue($salePrice, $vatRate) : 0;
        $estimatedPit = $taxEnabled ? $this->taxOnRevenue($salePrice, $pitRate) : 0;
        $estimatedTax = $estimatedVat + $estimatedPit;
        $grossProfit = $costPrice === null ? null : $salePrice - $costPrice;
        $netProfit = $grossProfit === null ? null : $grossProfit - $estimatedTax - $paymentFee - $otherCost;

        return [
            'gross_profit' => $grossProfit,
            'estimated_vat' => $estimatedVat,
            'estimated_pit' => $estimatedPit,
            'estimated_tax' => $estimatedTax,
            'net_profit' => $netProfit,
            'profit_margin' => $this->profitMargin($netProfit, $salePrice),
        ];
    }

    public function profitMargin(?int $netProfit, int $salePrice): ?float
    {
        if ($netProfit === null) {
            return null;
        }

        return $salePrice > 0 ? round(($netProfit / $salePrice) * 100, 4) : 0.0;
    }

    private function taxOnRevenue(int $salePrice, string $rate): int
    {
        $rateUnits = $this->rateUnits($rate);

        return intdiv(($salePrice * $rateUnits) + 500_000, 1_000_000);
    }

    private function rateUnits(string $rate): int
    {
        $normalized = trim($rate);

        if (preg_match('/^(\d{1,3})(?:\.(\d{1,4}))?$/', $normalized, $matches) !== 1) {
            throw new InvalidArgumentException('Tax rate must be a percentage with up to four decimal places.');
        }

        $whole = (int) $matches[1];
        $fraction = (int) str_pad($matches[2] ?? '', 4, '0');
        $units = ($whole * 10_000) + $fraction;

        if ($units > 1_000_000) {
            throw new InvalidArgumentException('Tax rate must not exceed 100 percent.');
        }

        return $units;
    }
}
