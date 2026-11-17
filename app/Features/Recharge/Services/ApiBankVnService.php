<?php

namespace App\Features\Recharge\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ApiBankVnService
{
    /** @param array{key: string, secret: string} $credentials
     * @return array<string, mixed>
     */
    public function call(array $credentials, string $method, string $path, array $payload = []): array
    {
        try {
            $response = Http::acceptJson()->asJson()->connectTimeout(3)->timeout(8)->withoutRedirecting()
                ->withHeaders(['X-API-KEY' => $credentials['key'], 'X-API-SECRET' => $credentials['secret']])
                ->send($method, 'https://apibankvn.com/api/v1'.$path, $method === 'POST' ? ['json' => $payload] : []);
        } catch (ConnectionException) {
            throw new RuntimeException('PROVIDER_CONNECTION');
        }
        if (! $response->successful() || strlen($response->body()) > 1048576) {
            throw new RuntimeException('PROVIDER_HTTP');
        }
        $data = $response->json();
        if (! is_array($data) || ($data['status'] ?? null) !== true || ! is_array($data['data'] ?? null)) {
            throw new RuntimeException('PROVIDER_RESPONSE');
        }

        return $data['data'];
    }

    /** @return list<array{bank_id: int, bank_code: string, bank_name: string, account_number: string, account_name: string}> */
    public function banks(array $credentials): array
    {
        $data = $this->call($credentials, 'GET', '/list-bank-accounts');
        if (! is_array($data['bank_accounts'] ?? null)) {
            throw new RuntimeException('PROVIDER_BANKS');
        }

        return collect($data['bank_accounts'])->filter(fn ($bank): bool => is_array($bank) && ($bank['status'] ?? '') === 'active' && (int) ($bank['bank_id'] ?? 0) > 0 && ! in_array(strtolower($bank['bank_code'] ?? ''), ['binance', 'binance-pay', 'binance_pay'], true))
            ->map(fn ($bank): array => ['bank_id' => (int) $bank['bank_id'], 'bank_code' => substr((string) ($bank['bank_code'] ?? ''), 0, 40), 'bank_name' => (string) ($bank['bank_name'] ?? ''), 'account_number' => (string) ($bank['account_number'] ?? ''), 'account_name' => (string) ($bank['account_name'] ?? '')])->values()->all();
    }

    public function order(array $credentials, string $code): array
    {
        abort_unless(preg_match('/\A[A-Za-z0-9_-]{1,100}\z/', $code), 422);

        return $this->extract($this->call($credentials, 'GET', '/recharge-orders/'.$code));
    }

    public function create(array $credentials, int $bank, int $amount, string $code): array
    {
        return $this->extract($this->call($credentials, 'POST', '/recharge-orders', ['bank_id' => $bank, 'amount' => $amount, 'client_order_code' => $code, 'comment' => $code]));
    }

    private function extract(array $data): array
    {
        if (! is_array($data['order'] ?? null)) {
            throw new RuntimeException('PROVIDER_ORDER');
        }

        return $data['order'];
    }
}
