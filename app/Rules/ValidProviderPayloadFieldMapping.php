<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidProviderPayloadFieldMapping implements ValidationRule
{
    private const RESERVED_OUTPUT_KEYS = [
        'account_info', 'amount', 'command', 'extra', 'game', 'partner_id', 'price', 'qty',
        'request_id', 'secret_key', 'server', 'service_code', 'sign', 'order_code',
    ];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            $fail('Trường :attribute phải là một JSON object.');

            return;
        }

        if (array_diff(array_keys($value), ['default', 'services']) !== []) {
            $fail('Trường :attribute chỉ hỗ trợ hai nhóm default và services.');

            return;
        }

        $default = $value['default'] ?? [];
        $services = $value['services'] ?? [];

        if (! $this->isValidMap($default) || ! is_array($services) || ($services !== [] && array_is_list($services))) {
            $fail('Cấu trúc mapping mặc định hoặc mapping theo dịch vụ không hợp lệ.');

            return;
        }

        if (count($services) > 100) {
            $fail('Mỗi provider chỉ được cấu hình tối đa 100 service mapping.');

            return;
        }

        foreach ($services as $serviceCode => $serviceMapping) {
            if (! is_string($serviceCode)
                || preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $serviceCode) !== 1
                || ! $this->isValidMap($serviceMapping)) {
                $fail('Mã dịch vụ và field mapping phải dùng chữ thường, số, dấu gạch dưới hoặc gạch ngang.');

                return;
            }

            $resolved = [...$default, ...$serviceMapping];
            if (count(array_unique(array_values($resolved))) !== count($resolved)) {
                $fail("Service {$serviceCode} có nhiều field nguồn trỏ tới cùng một field provider.");

                return;
            }
        }
    }

    private function isValidMap(mixed $mapping): bool
    {
        if (! is_array($mapping) || ($mapping !== [] && array_is_list($mapping)) || count($mapping) > 20) {
            return false;
        }

        foreach ($mapping as $source => $target) {
            if (! is_string($source)
                || ! is_string($target)
                || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $source) !== 1
                || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $target) !== 1
                || in_array($target, self::RESERVED_OUTPUT_KEYS, true)) {
                return false;
            }
        }

        return count(array_unique(array_values($mapping))) === count($mapping);
    }
}
