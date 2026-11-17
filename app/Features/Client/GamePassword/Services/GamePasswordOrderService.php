<?php

namespace App\Features\Client\GamePassword\Services;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\Wallet\Services\WalletService;
use App\Jobs\ChangeGamePassword;
use App\Models\GamePasswordOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GamePasswordOrderService
{
    public function __construct(private readonly GamePasswordConfigurationService $configuration, private readonly WalletService $wallets, private readonly ToolAvailabilityService $tools) {}

    public function create(User $user, array $entries, string $requestId, int $quotedPrice, array $captchaCredentials = []): Collection
    {
        $fingerprint = hash_hmac('sha256', json_encode([$entries, $captchaCredentials], JSON_THROW_ON_ERROR), config('app.key'));
        try {
            return DB::transaction(function () use ($user, $entries, $requestId, $quotedPrice, $fingerprint, $captchaCredentials): Collection {
                $locked = User::query()->lockForUpdate()->findOrFail($user->id);
                abort_unless($locked->status === 'active', 403);
                abort_if(DB::table('game_password_pruned_requests')->where('user_id', $locked->id)->where('request_id', $requestId)->exists(), 409, 'Yêu cầu cũ đã được dọn. Vui lòng tải lại trang để tạo yêu cầu mới.');
                $existing = GamePasswordOrder::query()->where('user_id', $locked->id)->where('request_id', $requestId)->orderBy('row_index')->get();
                if ($existing->isNotEmpty()) {
                    abort_unless($existing->count() === count($entries) && hash_equals($existing->first()->payload_hash, $fingerprint), 409, 'Mã yêu cầu đã được dùng với dữ liệu khác. Vui lòng tải lại trang.');

                    return $existing;
                }
                $configuration = $this->configuration->configuration();
                $this->validateCaptchaCredentials($captchaCredentials);
                if ($configuration['price'] === null) {
                    throw ValidationException::withMessages(['price' => 'Công cụ chưa được cấu hình đầy đủ. Vui lòng quay lại sau.']);
                }
                if ($configuration['price'] !== $quotedPrice) {
                    throw ValidationException::withMessages(['price' => 'Giá đã thay đổi. Vui lòng tải lại trang để xác nhận giá mới.']);
                }
                abort_unless($this->tools->find('game_password')['is_enabled'] ?? false, 403);
                $orders = collect();
                foreach ($entries as $index => $entry) {
                    $key = hash('sha256', mb_strtolower($entry['username']).'|'.$entry['server_code']);
                    if (GamePasswordOrder::query()->where('active_key', $key)->exists()) {
                        throw ValidationException::withMessages(['accounts_text' => 'Tài khoản ở dòng '.($index + 1).' đang có yêu cầu xử lý.']);
                    }
                    $order = GamePasswordOrder::query()->create([...$entry, 'user_id' => $locked->id, 'request_id' => $requestId,
                        'row_index' => $index, 'payload_hash' => $fingerprint, 'active_key' => $key, 'charged_amount' => $quotedPrice]);
                    $order->forceFill(['captcha_api_key' => $captchaCredentials['key'], 'captcha_api_secret' => $captchaCredentials['secret']]);
                    $charge = $this->wallets->apply($locked, $quotedPrice, 'debit', 'game-password:charge:'.$order->id, 'Đổi mật khẩu game #'.$order->id);
                    $order->appendProgressLog('queued');
                    $order->forceFill(['charge_transaction_id' => $charge->id, 'queued_at' => now()])->save();
                    $orders->push($order);
                }
                foreach ($orders as $order) {
                    ChangeGamePassword::dispatch($order->id)->beforeCommit();
                }

                return $orders;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['accounts_text' => 'Tài khoản đang có yêu cầu xử lý. Vui lòng kiểm tra lịch sử.']);
        }
    }

    public function history(array $filters, ?int $userId = null): LengthAwarePaginator
    {
        return GamePasswordOrder::query()->select(['id', 'user_id', 'username', 'server_code', 'status', 'progress_code', 'progress_logs', 'error_code', 'charged_amount', 'refunded_at', 'created_at', 'completed_at', 'attempt_version', 'queued_at'])
            ->selectRaw('CASE WHEN old_password IS NOT NULL AND new_password IS NOT NULL THEN 1 ELSE 0 END AS has_saved_credentials')
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->when($userId === null, fn ($query) => $query->with('user:id,username'))
            ->when(filled($filters['q'] ?? null), fn ($query) => $query->where('username', 'like', '%'.$filters['q'].'%'))
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->latest('id')->paginate(20, ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public function retry(User $actor, int $id, int $version, array $captchaCredentials = []): GamePasswordOrder
    {
        try {
            return DB::transaction(function () use ($actor, $id, $version, $captchaCredentials): GamePasswordOrder {
                $reference = GamePasswordOrder::findOrFail($id);
                $owner = User::query()->lockForUpdate()->findOrFail($reference->user_id);
                $order = GamePasswordOrder::query()->lockForUpdate()->findOrFail($id);
                abort_unless($actor->status === 'active' && ($actor->id === $owner->id || $actor->role === 'admin') && $owner->status === 'active', 403);
                abort_unless($order->canRetry() && $order->attempt_version === $version, 409, 'Chỉ được chạy lại đơn lỗi hoặc đơn pending quá 30 phút. Vui lòng tải lại lịch sử.');
                if (! $order->hasSavedCredentials()) {
                    throw ValidationException::withMessages(['order' => 'Đơn cũ không còn mật khẩu đã lưu nên chưa thể chạy lại.']);
                }
                $activeKey = hash('sha256', mb_strtolower($order->username).'|'.$order->server_code);
                abort_if(GamePasswordOrder::query()->where('active_key', $activeKey)->whereKeyNot($order->id)->exists(), 409, 'Tài khoản đang có một đơn khác xử lý.');
                if ($captchaCredentials !== []) {
                    $this->validateCaptchaCredentials($captchaCredentials);
                    $order->forceFill(['captcha_api_key' => $captchaCredentials['key'], 'captcha_api_secret' => $captchaCredentials['secret']]);
                }
                $this->validateCaptchaCredentials(['key' => $order->captcha_api_key, 'secret' => $order->captcha_api_secret]);
                if (! ($this->tools->find('game_password')['is_enabled'] ?? false)) {
                    throw ValidationException::withMessages(['old_password' => 'Công cụ chưa sẵn sàng. Vui lòng thử lại sau.']);
                }
                $order->forceFill([
                    'attempt_version' => $version + 1, 'verify_before_change' => true,
                    'active_key' => $activeKey, 'queued_at' => now(),
                    'status' => 'pending', 'progress_code' => 'queued', 'error_code' => null,
                    'processing_at' => null, 'change_submitted_at' => null, 'completed_at' => null,
                ])->save();
                $order->appendProgressLog('retry');
                $order->save();
                ChangeGamePassword::dispatch($order->id, $version + 1)->beforeCommit();

                return $order;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['old_password' => 'Tài khoản đang có một đơn khác xử lý.']);
        }
    }

    public function start(int $id, ?int $attemptVersion = null): ?GamePasswordOrder
    {
        return DB::transaction(function () use ($id, $attemptVersion): ?GamePasswordOrder {
            $reference = GamePasswordOrder::find($id);
            if (! $reference) {
                return null;
            }
            $user = User::query()->lockForUpdate()->find($reference->user_id);
            $order = GamePasswordOrder::query()->lockForUpdate()->find($id);
            if (! $user || ! $order || $order->status !== 'pending' || ($attemptVersion !== null && $order->attempt_version !== $attemptVersion)) {
                return null;
            }
            $order->forceFill(['status' => 'processing', 'progress_code' => 'login', 'processing_at' => now()])->save();
            if ($user->status !== 'active' || ! ($this->tools->find('game_password')['is_enabled'] ?? false)) {
                $this->finish($id, 'error', 'TOOL_UNAVAILABLE');

                return null;
            }

            return $order;
        }, 3);
    }

    public function progress(int $id, string $phase, ?int $attemptVersion = null): void
    {
        if (! isset(GamePasswordOrder::LOG_MESSAGES[$phase])) {
            return;
        }
        DB::transaction(function () use ($id, $phase, $attemptVersion): void {
            $order = GamePasswordOrder::query()->whereKey($id)->where('status', 'processing')
                ->when($attemptVersion !== null, fn ($query) => $query->where('attempt_version', $attemptVersion))->lockForUpdate()->first();
            if (! $order) {
                return;
            }
            $order->appendProgressLog($phase);
            $order->forceFill([
                'progress_code' => $phase, ...($phase === 'submitting' ? ['change_submitted_at' => now()] : []),
                ...($phase === 'change' ? ['verify_before_change' => false] : []),
            ])->save();
        }, 3);
    }

    public function finish(int $id, string $status, ?string $errorCode = null, bool $resolve = false, ?int $attemptVersion = null): GamePasswordOrder
    {
        return DB::transaction(function () use ($id, $status, $errorCode, $resolve, $attemptVersion): GamePasswordOrder {
            $reference = GamePasswordOrder::findOrFail($id);
            $user = User::query()->lockForUpdate()->findOrFail($reference->user_id);
            $order = GamePasswordOrder::query()->lockForUpdate()->findOrFail($id);
            if ($attemptVersion !== null && $order->attempt_version !== $attemptVersion) {
                return $order;
            }
            if ($resolve) {
                abort_unless($order->status === 'needs_review' && in_array($status, ['success', 'error'], true), 409);
            } elseif (! in_array($order->status, ['pending', 'processing'], true)) {
                return $order;
            }
            $refund = $resolve && $status === 'error'
                ? $this->wallets->apply($user, $order->charged_amount, 'credit', 'game-password:refund:'.$id, 'Hoàn phí đổi mật khẩu game #'.$id) : null;
            $clearCredentials = $status === 'success';
            $order->appendProgressLog($status);
            $order->appendProgressLog('done');
            $order->forceFill(['status' => $status, 'progress_code' => $status, 'error_code' => $errorCode,
                'completed_at' => now(),
                ...($clearCredentials ? ['old_password' => null, 'new_password' => null, 'captcha_api_key' => null, 'captcha_api_secret' => null] : []),
                'active_key' => $status === 'needs_review' ? $order->active_key : null,
                'refund_transaction_id' => $refund?->id ?? $order->refund_transaction_id, 'refunded_at' => $refund ? now() : $order->refunded_at])->save();

            return $order;
        }, 3);
    }

    private function validateCaptchaCredentials(array $credentials): void
    {
        foreach (['key' => 'captcha_api_key', 'secret' => 'captcha_api_secret'] as $key => $field) {
            if (! is_string($credentials[$key] ?? null) || ! preg_match('/^[\x21-\x7E]{1,512}$/', $credentials[$key])) {
                throw ValidationException::withMessages([$field => 'Nhập API Key và API Secret giaicaptcha.vn của bạn để giải captcha.']);
            }
        }
    }
}
