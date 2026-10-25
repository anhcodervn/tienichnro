<?php

namespace App\Features\Admin\User\Actions;

use App\Features\Admin\License\Services\LicenseAdminService;
use App\Models\License;
use App\Models\LicenseProduct;
use App\Models\User;
use Illuminate\Http\Request;

class SyncToolPermissionLicensesAction
{
    public function __construct(private readonly LicenseAdminService $licenses) {}

    /**
     * @param  list<int>  $productIds
     * @param  list<int>  $attachedIds
     */
    public function handle(User $user, array $productIds, array $attachedIds, Request $request): void
    {
        $removed = License::query()->where('user_id', $user->id)->where('is_permission_grant', true)
            ->whereNotIn('product_id', $productIds)->where('status', '!=', 'revoked')->orderBy('id')->lockForUpdate()->get();
        foreach ($removed as $license) {
            $this->licenses->manage($license, ['action' => 'revoke', 'reason' => 'Tool permission removed'], $request);
        }

        $products = LicenseProduct::query()->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get();
        foreach ($products as $product) {
            if ($user->role === User::ROLE_USER && License::query()->where('user_id', $user->id)
                ->where('product_id', $product->id)->where('status', '!=', 'revoked')->exists()) {
                continue;
            }
            $existing = License::query()->where('user_id', $user->id)->where('product_id', $product->id)
                ->where('is_permission_grant', true)->latest('id')->lockForUpdate()->first();
            if ($existing && (! in_array($product->id, $attachedIds, true) || $existing->status !== 'revoked')) {
                continue;
            }
            $this->licenses->issueForToolPermission($product, $user, $request);
        }
    }
}
