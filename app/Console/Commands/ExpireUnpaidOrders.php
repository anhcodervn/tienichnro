<?php

namespace App\Console\Commands;

use App\Features\Topup\Actions\ExpireUnpaidOrdersAction;
use Illuminate\Console\Command;

class ExpireUnpaidOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:expire-unpaid {--chunk=200 : Number of records processed per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire unpaid wallet deposits and game topup orders, then remove stale expired records';

    /**
     * Execute the console command.
     */
    public function handle(ExpireUnpaidOrdersAction $action): int
    {
        $result = $action->execute(max(1, (int) $this->option('chunk')));

        $this->info(sprintf(
            'Expired %d wallet deposit(s) and %d game order(s); deleted %d wallet deposit(s) and %d game order(s).',
            $result['expired_deposits'],
            $result['expired_orders'],
            $result['deleted_deposits'],
            $result['deleted_orders'],
        ));

        return self::SUCCESS;
    }
}
