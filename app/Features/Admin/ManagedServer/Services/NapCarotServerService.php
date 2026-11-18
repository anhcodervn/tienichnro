<?php

namespace App\Features\Admin\ManagedServer\Services;

use App\Models\ManagedServer;
use App\Support\SettingStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class NapCarotServerService
{
    private const ENDPOINT = 'https://napcarot.com/api/v1/catalog';

    public function __construct(private readonly SettingStore $settings) {}

    public function configuration(): array
    {
        return ['credentials_saved' => filled($this->settings->getString('managed_servers.napcarot.api_key'))
            && filled($this->settings->getString('managed_servers.napcarot.api_secret')), 'endpoint' => self::ENDPOINT];
    }

    public function save(array $data): array
    {
        if (filled($data['api_key'] ?? null)) {
            DB::transaction(function () use ($data): void {
                $this->settings->putEncryptedString('managed_servers.napcarot.api_key', $data['api_key']);
                $this->settings->putEncryptedString('managed_servers.napcarot.api_secret', $data['api_secret']);
            });
        }

        return $this->configuration();
    }

    public function sync(): array
    {
        if (! $this->configuration()['credentials_saved']) {
            throw ValidationException::withMessages(['api_key' => 'Lưu API key và secret Nạp Carot có quyền catalog:read trước khi đồng bộ.']);
        }
        $lock = Cache::lock('managed_servers:napcarot:sync', 30);
        abort_unless($lock->get(), 409, 'Một lượt đồng bộ máy chủ đang chạy.');
        try {
            try {
                $response = Http::acceptJson()->connectTimeout(3)->timeout(10)->withoutRedirecting()
                    ->withHeaders(['X-API-Key' => $this->settings->getString('managed_servers.napcarot.api_key'),
                        'X-API-Secret' => $this->settings->getString('managed_servers.napcarot.api_secret')])->get(self::ENDPOINT);
            } catch (ConnectionException) {
                throw ValidationException::withMessages(['api_key' => 'Không kết nối được Nạp Carot. Vui lòng thử lại.']);
            }
            if (! $response->successful() || strlen($response->body()) > 2097152 || $response->json('status') !== true || ! is_array($response->json('data'))) {
                throw ValidationException::withMessages(['api_key' => 'Không lấy được catalog. Kiểm tra key, quyền catalog:read và IP whitelist của Nạp Carot.']);
            }
            $games = collect($response->json('data'))->filter(fn ($game) => is_array($game) && ($game['slug'] ?? '') === 'ngoc-rong-online');
            $servers = $games->count() === 1 ? $games->first()['servers'] ?? null : null;
            $validator = Validator::make(['servers' => $servers], ['servers' => ['required', 'array', 'min:1', 'max:500'],
                'servers.*.id' => ['required', 'integer', 'min:1', 'distinct'], 'servers.*.name' => ['required', 'string', 'max:100']]);
            if ($validator->fails()) {
                throw ValidationException::withMessages(['catalog' => 'Catalog Ngọc Rồng không hợp lệ. Dữ liệu máy chủ hiện tại được giữ nguyên.']);
            }
            $rows = collect($servers)->values()->map(fn ($server, $index): array => ['type' => 'carot', 'server_id' => (string) $server['id'],
                'name' => $server['name'], 'is_active' => true, 'sort_order' => $index, 'created_at' => now(), 'updated_at' => now()])->all();
            DB::transaction(fn () => ManagedServer::query()->upsert($rows, ['type', 'server_id'], ['name', 'updated_at']));

            return ['count' => count($rows)];
        } finally {
            $lock->release();
        }
    }
}
