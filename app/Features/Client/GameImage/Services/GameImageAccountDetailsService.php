<?php

namespace App\Features\Client\GameImage\Services;

use App\Models\GameImageAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GameImageAccountDetailsService
{
    /** @param array{username: string, server: string, password?: ?string, capture_version: int} $data */
    public function update(GameImageAccount $account, array $data): GameImageAccount
    {
        return DB::transaction(function () use ($account, $data): GameImageAccount {
            User::query()->lockForUpdate()->findOrFail($account->user_id);
            $locked = GameImageAccount::query()->lockForUpdate()->findOrFail($account->id);
            abort_unless($locked->capture_version === $data['capture_version'], 409, 'Lượt chụp đã thay đổi. Vui lòng làm mới danh sách.');
            abort_if($locked->status === 'processing', 409, 'Tài khoản đang chụp ảnh. Hãy chờ lượt chụp kết thúc trước khi sửa.');
            if (GameImageAccount::query()->where('user_id', $locked->user_id)->where('username', $data['username'])->where('server', $data['server'])->whereKeyNot($locked->id)->exists()) {
                throw ValidationException::withMessages(['username' => 'Tài khoản này trên server đã có trong danh sách.']);
            }
            $locked->fill(['username' => $data['username'], 'server' => $data['server']]);
            if (filled($data['password'] ?? null)) {
                $locked->password = $data['password'];
            }
            if ($locked->isDirty(['username', 'server', 'password']) && $locked->status === 'pending') {
                $locked->forceFill(['capture_version' => $locked->capture_version + 1, 'capture_claim_hash' => null, 'capture_public_key' => null]);
            }
            $locked->save();

            return $locked;
        }, 3);
    }
}
