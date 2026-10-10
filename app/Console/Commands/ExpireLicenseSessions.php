<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Models\LicenseChallenge;
use App\Models\LicenseEvent;
use App\Models\LicenseNonce;
use App\Models\LicenseSession;
use Illuminate\Console\Command;

class ExpireLicenseSessions extends Command
{
    protected $signature = 'licenses:cleanup';

    protected $description = 'Mark elapsed license leases and prune retained security data';

    public function handle(): int
    {
        License::query()->whereIn('status', ['active', 'unused'])->where('expires_at', '<=', now())->update(['status' => 'expired']);
        LicenseSession::query()->where('status', 'active')->where('lease_expires_at', '<=', now())->update(['status' => 'expired']);
        LicenseNonce::query()->where('expires_at', '<', now())->delete();
        LicenseChallenge::query()->where('expires_at', '<', now())->delete();
        $cutoff = now()->subDays(config('license.retention_days'));
        LicenseSession::query()->where('status', '!=', 'active')->where('updated_at', '<', $cutoff)->delete();
        LicenseEvent::query()->where('created_at', '<', $cutoff)->delete();

        return self::SUCCESS;
    }
}
