<?php

namespace App\Console\Commands;

use App\Features\Admin\User\Actions\SyncToolPermissionLicensesAction;
use App\Models\License;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncToolPermissionLicenses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'licenses:sync-tool-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create missing perpetual licenses for existing tool permissions and revoke removed permissions';

    /**
     * Execute the console command.
     */
    public function handle(SyncToolPermissionLicensesAction $licenses): int
    {
        $count = 0;
        $users = User::query()->where(fn ($query) => $query->whereHas('permittedTools')
            ->orWhereIn('id', License::query()->select('user_id')->where('is_permission_grant', true)->where('status', '!=', 'revoked')));
        foreach ($users->lazyById(100) as $user) {
            DB::transaction(function () use ($user, $licenses): void {
                $user = User::query()->lockForUpdate()->findOrFail($user->id);
                $products = $user->permittedTools()->pluck('license_products.id')->map(fn ($id): int => (int) $id)->all();
                $licenses->handle($user, $products, [], new Request);
            }, 3);
            $count++;
        }
        $this->info('Synced '.$count.' accounts.');

        return self::SUCCESS;
    }
}
