<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidRobotsTxt implements ValidationRule
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
            $fail('robots.txt chỉ được chứa nội dung text hợp lệ.');

            return;
        }

        $hasUserAgent = false;

        foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^([a-z][a-z0-9-]*)\s*:\s*(.*)$/i', $line, $matches) !== 1) {
                $fail('Mỗi chỉ thị robots.txt phải có dạng Tên: giá trị.');

                return;
            }

            $directive = strtolower($matches[1]);
            $directiveValue = trim($matches[2]);
            $hasUserAgent = $hasUserAgent || $directive === 'user-agent';

            if ($directive === 'sitemap' && ! $this->isAbsoluteHttpUrl($directiveValue)) {
                $fail('Sitemap trong robots.txt phải là URL http/https đầy đủ.');

                return;
            }
        }

        if (! $hasUserAgent) {
            $fail('robots.txt phải có ít nhất một chỉ thị User-agent.');
        }
    }

    private function isAbsoluteHttpUrl(string $value): bool
    {
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
