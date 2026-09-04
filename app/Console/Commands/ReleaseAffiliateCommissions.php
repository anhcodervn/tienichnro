<?php

namespace App\Console\Commands;

use App\Features\Affiliate\Services\AffiliateCommissionService;
use Illuminate\Console\Command;

class ReleaseAffiliateCommissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'affiliate:release-commissions {--chunk=200 : Number of commissions loaded per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release eligible affiliate commissions after the seven-day holding period';

    /**
     * Execute the console command.
     */
    public function handle(AffiliateCommissionService $commissionService): int
    {
        $released = $commissionService->releaseDue(max(1, (int) $this->option('chunk')));
        $this->info("Released {$released} affiliate commission(s).");

        return self::SUCCESS;
    }
}
