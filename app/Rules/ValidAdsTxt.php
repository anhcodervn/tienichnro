<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidAdsTxt implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1 || preg_match('/<\/?[a-z][^>]*>/i', $value) === 1) {
            $fail('ads.txt chỉ được chứa nội dung text hợp lệ.');

            return;
        }

        foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $lineNumber => $line) {
            $line = trim(preg_replace('/\s+#.*$/', '', $line) ?? '');

            if ($line === '' || str_starts_with($line, '#') || preg_match('/^[A-Z][A-Z0-9_-]*\s*=/i', $line) === 1) {
                continue;
            }

            $fields = array_map('trim', explode(',', $line));
            $valid = count($fields) >= 3
                && count($fields) <= 4
                && preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $fields[0]) === 1
                && preg_match('/^[a-z0-9._-]{1,255}$/i', $fields[1]) === 1
                && in_array(strtoupper($fields[2]), ['DIRECT', 'RESELLER'], true)
                && (! isset($fields[3]) || preg_match('/^[a-z0-9._-]{1,255}$/i', $fields[3]) === 1);

            if (! $valid) {
                $fail('Dòng '.($lineNumber + 1).' của ads.txt không đúng định dạng domain, publisher ID, DIRECT/RESELLER.');

                return;
            }
        }
    }
}
