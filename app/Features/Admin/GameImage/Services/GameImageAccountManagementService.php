<?php

namespace App\Features\Admin\GameImage\Services;

use App\Features\Client\GameImage\Services\GameImageAccountDetailsService;
use App\Features\Client\GameImage\Services\GameImageOrderService;
use App\Models\GameImageAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GameImageAccountManagementService
{
    public function __construct(private readonly GameImageAccountDetailsService $details) {}

    /** @param array{username: string, server: string, password?: ?string, capture_version: int} $data */
    public function update(GameImageAccount $account, array $data): GameImageAccount
    {
        return $this->details->update($account, $data);
    }

    public function delete(GameImageAccount $account, int $version, GameImageOrderService $orders): void
    {
        DB::transaction(function () use ($account, $version, $orders): void {
            User::query()->lockForUpdate()->findOrFail($account->user_id);
            $locked = GameImageAccount::query()->lockForUpdate()->findOrFail($account->id);
            abort_unless($locked->capture_version === $version, 409, 'Lượt chụp đã thay đổi. Vui lòng làm mới danh sách.');
            if (in_array($locked->status, ['pending', 'processing', 'paused'], true)) {
                $locked = $orders->transition($locked, 'error', 'Admin đã xoá tài khoản và huỷ lượt chụp.', $version, null, 'ADMIN_DELETED');
            }
            $disk = $locked->capture_disk ?? 'local';
            $directory = $locked->capture_directory;
            $preview = $locked->preview_path;
            $id = $locked->id;
            $locked->delete();
            DB::afterCommit(function () use ($disk, $directory, $preview, $id): void {
                if ($directory !== null && ! str_contains($directory, '..') && ! str_contains($directory, '\\') &&
                    (($disk === 'local' && str_starts_with($directory, 'game-images/'.$id.'/captures/')) ||
                    ($disk === 'game-image-uploads' && preg_match('~\A[\p{L}\p{N}_-]+/account-'.$id.'-v\d+-[a-f0-9-]+\z~u', $directory)))) {
                    Storage::disk($disk)->deleteDirectory($directory);
                }
                if ($preview !== null && str_starts_with($preview, 'game-images/'.$id.'/previews/') && ! str_contains($preview, '..') && ! str_contains($preview, '\\')) {
                    Storage::disk('local')->delete($preview);
                }
            });
        }, 3);
    }
}
