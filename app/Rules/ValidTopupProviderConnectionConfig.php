<?php

namespace App\Rules;

use App\Features\Topup\Services\TopupProviderHttpClientFactory;
use App\Models\TopupProvider;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidTopupProviderConnectionConfig implements ValidationRule
{
    private const MAX_BYTES = 16384;

    private const MAX_DEPTH = 4;

    private const MAX_ITEMS = 50;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || array_is_list($value)) {
            $fail('Cấu hình kết nối phải là một JSON object.');

            return;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded) || strlen($encoded) > self::MAX_BYTES) {
            $fail('Cấu hình kết nối không được vượt quá 16 KB.');

            return;
        }

        $items = 0;

        if (! $this->hasValidValues($value, 1, $items)) {
            $fail('Cấu hình chỉ hỗ trợ tối đa 4 cấp, 50 trường; tên trường phải an toàn và giá trị chuỗi không quá 4096 ký tự.');

            return;
        }

        $balanceWarningThreshold = $value['balance_warning_threshold'] ?? null;

        if (
            array_key_exists('balance_warning_threshold', $value)
            && (! is_int($balanceWarningThreshold) || $balanceWarningThreshold < 0 || $balanceWarningThreshold > 1_000_000_000_000)
        ) {
            $fail('Ngưỡng cảnh báo số dư phải là số nguyên từ 0 đến 1.000.000.000.000đ.');

            return;
        }

        $minimumProfitPercent = $value['minimum_profit_percent'] ?? null;

        if (
            array_key_exists('minimum_profit_percent', $value)
            && (! is_numeric($minimumProfitPercent) || (float) $minimumProfitPercent < 0 || (float) $minimumProfitPercent > 99.99)
        ) {
            $fail('Phần trăm lợi nhuận tối thiểu phải từ 0 đến 99,99%.');

            return;
        }

        $baseUrl = $value['base_url'] ?? null;

        if (is_string($baseUrl) && $baseUrl !== '' && ! $this->hasValidBaseUrl($baseUrl)) {
            $fail('base_url phải là URL HTTP hoặc HTTPS hợp lệ và không được trỏ tới địa chỉ nội bộ.');

            return;
        }

        $proxyUrl = $value['proxy_url'] ?? $value['proxy'] ?? null;

        if (
            $proxyUrl !== null
            && $proxyUrl !== ''
            && $proxyUrl !== TopupProvider::SECRET_MASK
            && (! is_string($proxyUrl) || ! TopupProviderHttpClientFactory::isValidProxyUrl($proxyUrl))
        ) {
            $fail('Proxy phải có dạng http://user:pass@host:port, https://host:port, socks5://host:port hoặc socks5h://host:port.');
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function hasValidValues(array $values, int $depth, int &$items): bool
    {
        if ($depth > self::MAX_DEPTH) {
            return false;
        }

        foreach ($values as $key => $value) {
            $items++;

            if ($items > self::MAX_ITEMS
                || ! is_string($key)
                || $key === '__encrypted'
                || preg_match('/\A[a-zA-Z][a-zA-Z0-9_.-]{0,99}\z/', $key) !== 1) {
                return false;
            }

            if (is_array($value)) {
                if (array_is_list($value) || ! $this->hasValidValues($value, $depth + 1, $items)) {
                    return false;
                }

                continue;
            }

            if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value) && $value !== null) {
                return false;
            }

            if (is_string($value) && mb_strlen($value) > 4096) {
                return false;
            }
        }

        return true;
    }

    private function hasValidBaseUrl(string $baseUrl): bool
    {
        $parts = parse_url($baseUrl);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;

        if (! is_string($host) || ! in_array($scheme, ['http', 'https'], true) || mb_strlen($baseUrl) > 500) {
            return false;
        }

        if (strtolower($host) === 'localhost' || str_ends_with(strtolower($host), '.localhost')) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP) === false
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
