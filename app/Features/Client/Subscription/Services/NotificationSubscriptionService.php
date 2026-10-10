<?php

namespace App\Features\Client\Subscription\Services;

use App\Features\Client\Wallet\Services\WalletService;
use App\Models\NotificationSubscription;
use App\Models\NotificationWebhookDelivery;
use App\Models\ServiceOffering;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NotificationSubscriptionService
{
    public function __construct(private WalletService $wallets) {}

    /** @return Builder<ServicePackage> */
    public function availablePackages(): Builder
    {
        $pagePath = rtrim(route('notification-subscriptions.index', absolute: false), '/');
        $serviceCodes = ServiceOffering::query()->where('is_enabled', true)->get(['code', 'url', 'page_slug'])
            ->filter(fn (ServiceOffering $service): bool => filled($service->page_slug) ? $service->page_slug === ltrim($pagePath, '/') : rtrim((string) parse_url($service->url ?? '', PHP_URL_PATH), '/') === $pagePath)
            ->pluck('code');

        return ServicePackage::query()->with('service')->where('is_active', true)
            ->whereIn('service_code', $serviceCodes)->orderBy('sort_order')->orderBy('id');
    }

    /** @param array<string, mixed> $data */
    public function purchase(User $user, array $data): NotificationSubscription
    {
        return DB::transaction(function () use ($user, $data): NotificationSubscription {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $existing = NotificationSubscription::query()->where('user_id', $user->id)->where('request_id', $data['request_id'])->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw ValidationException::withMessages(['request_id' => 'Yêu cầu này đã dùng cho cấu hình khác.']);
                }

                return $existing;
            }
            $package = ServicePackage::query()->lockForUpdate()->findOrFail($data['package_id']);
            if (! $this->availablePackages()->whereKey($package->id)->exists()) {
                throw ValidationException::withMessages(['package_id' => 'Gói dịch vụ đang ngừng cung cấp.']);
            }
            $mode = $package->notification_mode ?? 'service';
            $current = NotificationSubscription::query()->where('user_id', $user->id)->when($mode === 'service', fn (Builder $query) => $query->where('service_code', $package->service_code))->where('mode', $mode)->where('status', 'active')->get();
            if ($current->contains(fn (NotificationSubscription $subscription): bool => $subscription->hasAccess() || NotificationWebhookDelivery::query()->where('subscription_id', $subscription->id)->whereIn('status', ['pending', 'processing'])->exists())) {
                throw ValidationException::withMessages(['package_id' => 'Bạn đang có gói này còn hiệu lực. Liên hệ hỗ trợ để nâng gói.']);
            }
            $servicePayload = app(ServicePayloadService::class)->validate($package->service?->payload_fields ?? [], $data['service_payload'] ?? []);
            $transaction = $this->wallets->apply($user, $package->price, 'debit', 'subscription:'.$data['request_id'], 'Đăng ký '.$package->name);

            $subscription = NotificationSubscription::query()->create([
                'user_id' => $user->id, 'service_package_id' => $package->id, 'wallet_transaction_id' => $transaction->id,
                'request_id' => $data['request_id'], 'request_hash' => $hash, 'mode' => $mode, 'status' => 'active',
                'service_code' => $package->service_code, 'package_name' => $package->name, 'price' => $package->price,
                'billing_type' => $package->billing_type, 'starts_at' => now(),
                'expires_at' => $package->billing_type === 'time' ? now()->addDays($package->duration_days) : null,
                'remaining_uses' => $package->billing_type === 'usage' ? $package->usage_limit : null,
                ...$this->configuration($mode, $data),
                'webhook_secret' => $package->notification_mode === 'webhook' ? Str::random(64) : null,
            ]);
            $subscription->service_payload = $servicePayload;
            $subscription->save();

            return $subscription;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, NotificationSubscription $subscription, array $data): NotificationSubscription
    {
        abort_unless($subscription->user_id === $user->id, 404);

        return DB::transaction(function () use ($user, $subscription, $data): NotificationSubscription {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $subscription = NotificationSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if (! $subscription->hasAccess()) {
                throw ValidationException::withMessages(['subscription' => 'Gói đã hết hiệu lực.']);
            }
            $subscription->update($this->configuration($subscription->mode, $data));

            return $subscription;
        }, 3);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function configuration(string $mode, array $data): array
    {
        if ($mode === 'service') {
            return [];
        }
        if (($mode === 'personal' && (! isset($data['zalo_id'], $data['characters'], $data['notification_types']) || count($data['characters']) > 10)) || ($mode === 'webhook' && ! isset($data['webhook_url']))) {
            throw ValidationException::withMessages(['package_id' => 'Loại gói hoặc cấu hình không hợp lệ. Vui lòng tải lại trang.']);
        }

        return $mode === 'personal' ? ['zalo_id' => $data['zalo_id'], 'characters' => $data['characters'], 'notification_types' => $data['notification_types'], 'webhook_url' => null]
            : ['webhook_url' => $data['webhook_url'], 'zalo_id' => null, 'characters' => null, 'notification_types' => null];
    }
}
