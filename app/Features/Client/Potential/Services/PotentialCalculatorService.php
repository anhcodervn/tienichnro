<?php

namespace App\Features\Client\Potential\Services;

class PotentialCalculatorService
{
    /** @return array<string, array{label: string, hp: int, ki: int, attack: int}> */
    public function planets(): array
    {
        return [
            'earth' => ['label' => 'Trái Đất', 'hp' => 200, 'ki' => 100, 'attack' => 12],
            'namec' => ['label' => 'Namec', 'hp' => 100, 'ki' => 200, 'attack' => 12],
            'saiyan' => ['label' => 'Xayda', 'hp' => 100, 'ki' => 100, 'attack' => 15],
        ];
    }

    /**
     * @param  array{planet: string, hp: int|string, ki: int|string, attack: int|string, armor: int|string, critical: int|string}  $stats
     * @return array{planet: string, breakdown: array<string, float>, total: float, level: ?string}
     */
    public function calculate(array $stats): array
    {
        $planet = $this->planets()[$stats['planet']];
        $armor = (int) $stats['armor'];
        $breakdown = [
            'hp' => $this->series((int) $stats['hp'] + 980, $planet['hp'] + 1000, 20),
            'ki' => $this->series((int) $stats['ki'] + 980, $planet['ki'] + 1000, 20),
            'attack' => $this->series((int) $stats['attack'] * 100 - 100, $planet['attack'] * 100, 100),
            'armor' => (float) ($armor * (1000000 + ($armor - 1) * 100000) / 2),
            'critical' => (float) (50000000 * (5 ** (int) $stats['critical'] - 1) / 4),
        ];
        $total = (float) array_sum($breakdown);

        return ['planet' => $planet['label'], 'breakdown' => $breakdown, 'total' => $total, 'level' => $this->level($total, $stats['planet'])];
    }

    private function series(int $last, int $first, int $step): float
    {
        return ($last + $first) * (($last - $first) / $step + 1) / 2;
    }

    public function level(float $total, string $planet): ?string
    {
        $super = match ($planet) {
            'earth' => 'Siêu Nhân', 'namec' => 'Siêu Namec', 'saiyan' => 'Siêu Xayda'
        };
        $god = 'Thần '.$this->planets()[$planet]['label'];
        foreach ([
            [1200, null], [340000, 'Tân Binh'], [1500000, 'Vệ Binh'],
            [15000000, $super.' Cấp 1'], [150000000, $super.' Cấp 2'],
            [1500000000, $super.' Cấp 3'], [16700000000, $super.' Cấp 4'],
            [40000000000, $god.' Cấp 1'], [50000000000, $god.' Cấp 2'],
            [60000000000, $god.' Cấp 3'], [70000000000, 'Giới Vương Thần Cấp 1'],
            [80000000000, 'Giới Vương Thần Cấp 2'], [100000000000, 'Giới Vương Thần Cấp 3'],
        ] as [$upper, $label]) {
            if ($total < $upper) {
                return $label;
            }
        }

        return 'Thần Hủy Diệt';
    }
}
