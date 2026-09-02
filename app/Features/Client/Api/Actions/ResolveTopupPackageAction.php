<?php

namespace App\Features\Client\Api\Actions;

use App\Models\TopupPackage;
use Illuminate\Validation\ValidationException;

class ResolveTopupPackageAction
{
    public function handle(int $gameId, int $denomination): TopupPackage
    {
        $packages = TopupPackage::query()
            ->active()
            ->where('game_id', $gameId)
            ->where('denomination', $denomination)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($packages->count() !== 1) {
            $message = $packages->isEmpty()
                ? 'Không tìm thấy gói nạp phù hợp với game và mệnh giá đã chọn.'
                : 'Có nhiều gói nạp trùng game và mệnh giá. Vui lòng liên hệ quản trị viên.';

            throw ValidationException::withMessages(['price' => $message]);
        }

        return $packages->firstOrFail();
    }
}
