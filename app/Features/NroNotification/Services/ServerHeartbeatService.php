<?php

namespace App\Features\NroNotification\Services;

use App\Models\NroServer;
use Illuminate\Database\Eloquent\Builder;

class ServerHeartbeatService
{
    public function ping(int $serverCode): NroServer
    {
        $server = NroServer::query()->where('server_code', $serverCode)->where('is_active', true)->firstOrFail();
        $server->forceFill(['connection' => true, 'last_ping_at' => now()])->save();

        return $server;
    }

    public function expire(): int
    {
        return NroServer::query()->where('connection', true)
            ->where(function (Builder $query): void {
                $query->whereNull('last_ping_at')
                    ->orWhere('last_ping_at', '<=', now()->subSeconds(NroServer::heartbeatTimeoutSeconds()));
            })->update(['connection' => false]);
    }
}
