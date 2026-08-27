<?php

namespace App\Features\Client\Api\Actions;

use App\Models\TopupPackage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ResolveTopupPackageAction
{
    public function handle(int $gameId, int $serverId, int $denomination): TopupPackage
    {
        /** @var Collection<int, TopupPackage> $packages */
        $packages = TopupPackage::query()
            ->active()
            ->where('game_id', $gameId)
            ->where('denomination', $denomination)
            ->where(function ($query) use ($serverId): void {
                $query->where('game_server_id', $serverId)
                    ->orWhereNull('game_server_id');
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $serverPackages = $packages->where('game_server_id', $serverId)->values();
        $matchingPackages = $serverPackages->isNotEmpty()
            ? $serverPackages
            : $packages->whereNull('game_server_id')->values();

        if ($matchingPackages->count() !== 1) {
            $message = $matchingPackages->isEmpty()
                ? 'Không tìm thấy gói nạp phù hợp với game, server và mệnh giá đã chọn.'
                : 'Có nhiều gói nạp trùng game, server và mệnh giá. Vui lòng liên hệ quản trị viên.';

            throw ValidationException::withMessages(['price' => $message]);
        }

        return $matchingPackages->firstOrFail();
    }
}
