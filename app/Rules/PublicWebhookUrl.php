<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PublicWebhookUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $this->endpoint($value) === null) {
            $fail('Webhook phải là URL HTTP/HTTPS công khai, không dùng mạng nội bộ hoặc cổng khác 80/443.');
        }
    }

    /** @return array{host: string, port: int, ip: string}|null */
    public function endpoint(string $url): ?array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || preg_match('/[\x00-\x20\x7f]/', $url) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = trim($parts['host'] ?? '', '[]');
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || $port !== ($scheme === 'https' ? 443 : 80)) {
            return null;
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->addresses($host);
        if ($addresses === []) {
            return null;
        }
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)
                || str_starts_with(strtolower($address), '::ffff:')
                || (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (int) explode('.', $address)[0] >= 224)
                || (str_contains($address, ':') && ! preg_match('/\A[23][0-9a-f]{3}:/i', $address))
                || preg_match('/\A100\.(?:6[4-9]|[7-9][0-9]|1[01][0-9]|12[0-7])\./', $address)) {
                return null;
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $addresses[0]];
    }

    /** @return list<string> */
    protected function addresses(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (! is_array($records)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null, $records))));
    }
}
