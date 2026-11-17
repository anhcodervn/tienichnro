<?php

namespace App\Features\Client\GameImage\Services;

use App\Features\Client\Wallet\Services\WalletService;
use App\Models\GameImageAccount;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GameImageOrderService
{
    public function __construct(private readonly SettingStore $settings, private readonly WalletService $wallets) {}

    /** @return array{price: ?int} */
    public function configuration(): array
    {
        $price = $this->settings->get('game_image.price');

        return ['price' => $price === null ? null : (int) $price];
    }

    public function configure(int $price): array
    {
        $this->settings->putMany(['game_image.price' => $price]);

        return $this->configuration();
    }

    /** @param array{username: string, password: string, server: string} $data */
    public function create(User $user, array $data): GameImageAccount
    {
        return DB::transaction(function () use ($user, $data): GameImageAccount {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($lockedUser->status === 'active', 403);
            $price = $this->configuration()['price'];
            if ($price === null) {
                throw ValidationException::withMessages(['price' => 'Chưa cấu hình phí ghép ảnh. Vui lòng quay lại sau.']);
            }
            if (GameImageAccount::query()->where('user_id', $user->id)->where('username', $data['username'])->where('server', $data['server'])->exists()) {
                throw ValidationException::withMessages(['username' => 'Tài khoản này trên server đã có trong danh sách.']);
            }
            $account = GameImageAccount::query()->create([...$data, 'user_id' => $user->id]);
            $transaction = $this->wallets->apply($lockedUser, $price, 'debit', 'game-image:charge:'.$account->id, 'Ghép ảnh game #'.$account->id);
            $account->forceFill(['status' => 'pending', 'charged_amount' => $price, 'charge_transaction_id' => $transaction->id])->save();

            return $account;
        }, 3);
    }

    public function recapture(GameImageAccount $account, int $version, int $quotedPrice): GameImageAccount
    {
        return DB::transaction(function () use ($account, $version, $quotedPrice): GameImageAccount {
            $user = User::query()->lockForUpdate()->findOrFail($account->user_id);
            abort_unless($user->status === 'active', 403);
            $locked = GameImageAccount::query()->lockForUpdate()->findOrFail($account->id);
            abort_unless($locked->capture_version === $version && in_array($locked->status, ['success', 'error'], true), 409, 'Lượt chụp đã thay đổi hoặc đang xử lý. Vui lòng tải lại trang.');
            $price = $this->configuration()['price'];
            if ($price === null || $price !== $quotedPrice) {
                throw ValidationException::withMessages(['price' => 'Phí chụp ảnh đã thay đổi. Vui lòng tải lại trang để xác nhận mức phí mới.']);
            }
            $nextVersion = $version + 1;
            $transaction = $this->wallets->apply($user, $price, 'debit', 'game-image:charge:'.$locked->id.':'.$nextVersion, 'Chụp lại ảnh game #'.$locked->id.' lượt '.$nextVersion);
            $locked->forceFill(['capture_version' => $nextVersion, 'status' => 'pending', 'charged_amount' => $price,
                'charge_transaction_id' => $transaction->id, 'refund_transaction_id' => null, 'refunded_at' => null,
                'error_code' => null, 'error_message' => null, 'processing_at' => null, 'completed_at' => null, 'capture_claim_hash' => null, 'capture_public_key' => null])->save();

            return $locked;
        }, 3);
    }

    public function transition(GameImageAccount $account, string $status, ?string $message, int $version = 1, ?string $claimToken = null, ?string $errorCode = null): GameImageAccount
    {
        if (! in_array($status, ['processing', 'error'], true) || ($status === 'error' && blank($message))) {
            throw ValidationException::withMessages(['status' => 'Trạng thái hoặc thông báo lỗi không hợp lệ.']);
        }

        return DB::transaction(function () use ($account, $status, $message, $version, $claimToken, $errorCode): GameImageAccount {
            $user = User::query()->lockForUpdate()->findOrFail($account->user_id);
            $locked = GameImageAccount::query()->lockForUpdate()->findOrFail($account->id);
            abort_unless($locked->capture_version === $version, 409, 'Lượt chụp không còn hiệu lực.');
            if ($claimToken !== null) {
                abort_unless(filled($locked->capture_claim_hash) && hash_equals($locked->capture_claim_hash, hash('sha256', $claimToken)), 409, 'Capture claim is invalid.');
            }
            if ($locked->status === $status) {
                return $locked;
            }
            abort_unless(in_array($locked->status, ['pending', 'processing'], true), 409, 'Lượt ghép ảnh đã kết thúc, không thể đổi trạng thái.');
            if ($status === 'processing') {
                $locked->forceFill(['status' => 'processing', 'processing_at' => now()])->save();
            } else {
                $refund = $locked->charge_transaction_id !== null || $locked->charged_amount > 0
                    ? $this->wallets->apply($user, $locked->charged_amount, 'credit', 'game-image:refund:'.$locked->id.($version > 1 ? ':'.$version : ''), 'Hoàn tiền ghép ảnh lỗi #'.$locked->id) : null;
                $locked->forceFill(['status' => 'error', 'error_code' => $errorCode, 'error_message' => $message, 'completed_at' => now(),
                    'refund_transaction_id' => $refund?->id, 'refunded_at' => $refund ? now() : null])->save();
            }

            return $locked;
        }, 3);
    }
}
