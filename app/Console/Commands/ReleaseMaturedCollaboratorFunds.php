<?php

namespace App\Console\Commands;

use App\Features\Affiliate\Services\AffiliateWalletService;
use App\Models\GameServiceOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseMaturedCollaboratorFunds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game-service-orders:release-collaborator-funds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Giải ngân tiền công CTV của đơn dịch vụ đã hoàn thành đủ ba ngày';

    /**
     * Execute the console command.
     */
    public function handle(AffiliateWalletService $walletService): int
    {
        $released = 0;

        GameServiceOrder::query()
            ->where('status', 'completed')
            ->whereNotNull('collaborator_held_at')
            ->whereNotNull('collaborator_available_at')
            ->where('collaborator_available_at', '<=', now())
            ->whereNull('collaborator_settled_at')
            ->whereNull('collaborator_refunded_at')
            ->select('id')
            ->chunkById(100, function ($orders) use ($walletService, &$released): void {
                foreach ($orders as $order) {
                    DB::transaction(function () use ($order, $walletService, &$released): void {
                        $locked = GameServiceOrder::query()->lockForUpdate()->find($order->id);

                        if ($locked instanceof GameServiceOrder && $walletService->releaseMaturedGameServiceOrder($locked) !== null) {
                            $released++;
                        }
                    }, 3);
                }
            });

        $this->components->info("Đã giải ngân {$released} đơn dịch vụ.");

        return self::SUCCESS;
    }
}
