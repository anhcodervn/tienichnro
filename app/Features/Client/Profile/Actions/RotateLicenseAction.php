<?php

namespace App\Features\Client\Profile\Actions;

use App\Features\Admin\License\Services\LicenseAdminService;
use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RotateLicenseAction
{
    public function __construct(private readonly LicenseAdminService $licenses) {}

    public function handle(License $license, Request $request): void
    {
        DB::transaction(function () use ($license, $request): void {
            $license = License::query()->whereKey($license->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($request->user())->authorize('rotate', $license);
            $this->licenses->rotate($license, 'Chủ sở hữu đổi key từ dashboard', $request);
        }, 3);
    }
}
