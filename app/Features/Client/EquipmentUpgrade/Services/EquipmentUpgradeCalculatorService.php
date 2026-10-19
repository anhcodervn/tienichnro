<?php

namespace App\Features\Client\EquipmentUpgrade\Services;

class EquipmentUpgradeCalculatorService
{
    /** @return list<array{level: int, value: float, rounded: int, is_current: bool}> */
    public function calculate(float $currentStat, int $currentLevel): array
    {
        $values = [$currentLevel => $currentStat];
        for ($level = $currentLevel - 1; $level >= 0; $level--) {
            $next = $this->scriptRound($values[$level + 1]);
            $values[$level] = -$this->scriptRound($next / 11 - $next);
        }
        for ($level = $currentLevel + 1; $level <= 7; $level++) {
            $previous = $this->scriptRound($values[$level - 1]);
            $values[$level] = $previous * 0.1 + $previous;
        }
        $results = [];
        for ($level = 0; $level <= 7; $level++) {
            $results[] = [
                'level' => $level,
                'value' => (float) $values[$level],
                'rounded' => (int) $this->scriptRound($values[$level]),
                'is_current' => $level === $currentLevel,
            ];
        }

        return $results;
    }

    /** Matches JavaScript Math.round, including negative halfway values. */
    private function scriptRound(float $value): float
    {
        return floor($value + 0.5);
    }
}
