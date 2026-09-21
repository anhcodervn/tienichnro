<?php

use App\Enums\TaxCalculationType;
use App\Features\Topup\Services\OrderProfitCalculatorService;

test('it calculates estimated revenue tax and net profit from the full sale amount', function (): void {
    $result = app(OrderProfitCalculatorService::class)->calculate(
        costPrice: 803_000,
        salePrice: 805_000,
        vatRate: '1.0000',
        pitRate: '0.5000',
        taxEnabled: true,
    );

    expect($result)->toMatchArray([
        'gross_profit' => 2_000,
        'estimated_vat' => 8_050,
        'estimated_pit' => 4_025,
        'estimated_tax' => 12_075,
        'net_profit' => -10_075,
        'profit_margin' => -1.2516,
    ]);
});

test('it handles disabled tax fees and zero revenue without division by zero', function (): void {
    $calculator = app(OrderProfitCalculatorService::class);

    expect($calculator->calculate(80_000, 90_000, '1.0000', '0.5000', false, paymentFee: 500, otherCost: 250))
        ->toMatchArray([
            'gross_profit' => 10_000,
            'estimated_tax' => 0,
            'net_profit' => 9_250,
        ])
        ->and($calculator->calculate(0, 0, '1.0000', '0.5000', true)['profit_margin'])->toBe(0.0);
});

test('it keeps profit unknown when provider cost is unavailable and rounds each tax separately', function (): void {
    $result = app(OrderProfitCalculatorService::class)->calculate(null, 101, '1.0000', '0.5000', true);

    expect($result)->toMatchArray([
        'gross_profit' => null,
        'estimated_vat' => 1,
        'estimated_pit' => 1,
        'estimated_tax' => 2,
        'net_profit' => null,
        'profit_margin' => null,
    ]);
});

test('profit based tax cannot be selected before the calculation method is implemented', function (): void {
    expect(fn () => app(OrderProfitCalculatorService::class)->calculate(
        80_000,
        90_000,
        '1.0000',
        '0.5000',
        true,
        TaxCalculationType::Profit,
    ))->toThrow(LogicException::class);
});
