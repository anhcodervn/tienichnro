<?php

namespace App\Console\Commands;

use App\Features\NroNotification\Services\ServerHeartbeatService;
use Illuminate\Console\Command;

class ExpireNroServerConnections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nro:expire-server-connections';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark NRO servers offline after missed bot heartbeats';

    /**
     * Execute the console command.
     */
    public function handle(ServerHeartbeatService $service): int
    {
        $this->info('Expired connections: '.$service->expire());

        return self::SUCCESS;
    }
}
