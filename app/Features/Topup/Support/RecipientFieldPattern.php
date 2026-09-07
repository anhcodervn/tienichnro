<?php

namespace App\Features\Topup\Support;

final class RecipientFieldPattern
{
    private const DELIMITERS = ['~', '#', '%', '!', '@', ';', '`', '/'];

    public function isValid(string $pattern): bool
    {
        $compiledPattern = $this->compile($pattern);

        return $compiledPattern !== null && @preg_match($compiledPattern, '') !== false;
    }

    public function matches(string $pattern, string $value): bool
    {
        $compiledPattern = $this->compile($pattern);

        return $compiledPattern !== null && @preg_match($compiledPattern, $value) === 1;
    }

    private function compile(string $pattern): ?string
    {
        foreach (self::DELIMITERS as $delimiter) {
            if (! str_contains($pattern, $delimiter)) {
                return $delimiter.'(*LIMIT_MATCH=100000)(*LIMIT_RECURSION=10000)'.$pattern.$delimiter.'u';
            }
        }

        return null;
    }
}
