<?php

namespace App\Features\NroNotification\Services;

use App\Models\NroServer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ServerHeartbeatService
{
    public function __construct(private readonly NotificationReadCacheService $readCache) {}

    public function ping(int $serverCode): NroServer
    {
        $server = NroServer::query()->where('server_code', $serverCode)->where('is_active', true)->firstOrFail();
        $server->forceFill(['connection' => true, 'last_ping_at' => now()])->save();

        return $server;
    }

    public function expire(): int
    {
        $expired = NroServer::query()->where('connection', true)
            ->where(function (Builder $query): void {
                $query->whereNull('last_ping_at')
                    ->orWhere('last_ping_at', '<=', now()->subSeconds(NroServer::heartbeatTimeoutSeconds()));
            })->update(['connection' => false]);
        if ($expired > 0) {
            DB::afterCommit(function (): void {
                $this->readCache->invalidate('data');
                $this->readCache->invalidate('catalog');
            });
        }

        return $expired;
    }
}
