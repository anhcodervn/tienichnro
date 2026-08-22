<?php

namespace App\Service;

use App\Exceptions\ApiException;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class DiscordWebhookNotifier
{
    public function __construct(
        private readonly SettingStore $settingStore,
    ) {}

    /**
     * @return array<int, array{label:string,value:string}>
     */
    public function eventOptions(): array
    {
        return [
            ['label' => 'Ping kiểm tra', 'value' => 'test_ping'],
            ['label' => 'Đăng ký mới', 'value' => 'user_registered'],
            ['label' => 'Nạp tiền thành công', 'value' => 'recharge_success'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function configuredWebhooks(): array
    {
        $items = $this->settingStore->getArray('discord_webhooks', []);

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): array {
                $events = collect(Arr::get($item, 'events', []))
                    ->filter(fn (mixed $event): bool => is_string($event) && trim($event) !== '')
                    ->values()
                    ->all();

                return [
                    'name' => trim((string) Arr::get($item, 'name', 'Webhook Discord')),
                    'url' => trim((string) Arr::get($item, 'url', '')),
                    'is_active' => (bool) Arr::get($item, 'is_active', false),
                    'events' => $events,
                ];
            })
            ->filter(fn (array $item): bool => $item['url'] !== '')
            ->values()
            ->all();
    }

    public function sendUserRegistered(User $user): void
    {
        $this->notify('user_registered', [
            'title' => 'Người dùng mới đăng ký',
            'description' => 'Hệ thống vừa có tài khoản mới.',
            'fields' => [
                ['name' => 'User', 'value' => (string) ($user->full_name ?: $user->username), 'inline' => true],
                ['name' => 'Email', 'value' => (string) ($user->email ?: '--'), 'inline' => true],
                ['name' => 'Thời gian', 'value' => (string) now()->format('H:i d/m/Y'), 'inline' => false],
            ],
            'color' => 0x1D4ED8,
        ]);
    }

    public function sendRechargeSuccess(PaymentTransaction $paymentTransaction, ?User $user = null): void
    {
        $owner = $user ?? $paymentTransaction->user;

        $this->notify('recharge_success', [
            'title' => 'Nạp tiền thành công',
            'description' => 'Ví người dùng đã được cộng tiền.',
            'fields' => [
                ['name' => 'Mã giao dịch', 'value' => $paymentTransaction->transaction_code, 'inline' => true],
                ['name' => 'Số tiền', 'value' => number_format((float) $paymentTransaction->amount, 0, ',', '.').' đ', 'inline' => true],
                ['name' => 'Người dùng', 'value' => (string) ($owner?->full_name ?: $owner?->username ?: $owner?->email ?: '--'), 'inline' => false],
            ],
            'color' => 0x16A34A,
        ]);
    }

    public function sendTestWebhook(int $webhookIndex, string $eventKey): void
    {
        $webhooks = $this->configuredWebhooks();
        $webhook = $webhooks[$webhookIndex] ?? null;

        if (! is_array($webhook)) {
            throw new ApiException('Webhook Discord không tồn tại hoặc đã bị xoá.', 422);
        }

        $this->dispatch(
            webhook: $webhook,
            eventKey: $eventKey,
            payload: [
                'title' => 'Webhook kiểm tra hoạt động',
                'description' => 'Kết nối Discord webhook từ trang quản trị hoạt động bình thường.',
                'fields' => [
                    ['name' => 'Sự kiện', 'value' => $eventKey, 'inline' => true],
                    ['name' => 'Webhook', 'value' => (string) $webhook['name'], 'inline' => true],
                ],
                'color' => 0x0891B2,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notify(string $eventKey, array $payload): void
    {
        collect($this->configuredWebhooks())
            ->filter(fn (array $webhook): bool => (bool) $webhook['is_active'] && in_array($eventKey, $webhook['events'], true))
            ->each(function (array $webhook) use ($eventKey, $payload): void {
                try {
                    $this->dispatch($webhook, $eventKey, $payload);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $webhook
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(array $webhook, string $eventKey, array $payload): void
    {
        $url = trim((string) Arr::get($webhook, 'url', ''));

        if ($url === '') {
            throw new ApiException('Webhook Discord chưa được cấu hình URL.', 422);
        }

        Http::timeout(8)
            ->connectTimeout(4)
            ->acceptJson()
            ->retry(1, 300, throw: false)
            ->post($url, [
                'username' => (string) config('services.discord.bot_name', 'Nạp Carot Monitor'),
                'avatar_url' => (string) config('services.discord.bot_avatar_url', ''),
                'embeds' => [[
                    'title' => Arr::get($payload, 'title', 'Thông báo hệ thống'),
                    'description' => Arr::get($payload, 'description', ''),
                    'color' => Arr::get($payload, 'color', 0x2563EB),
                    'fields' => array_merge(
                        Arr::get($payload, 'fields', []),
                        $this->contextFields(),
                    ),
                    'footer' => [
                        'text' => sprintf(
                            'Event: %s • %s • %s',
                            $eventKey,
                            trim((string) config('services.discord.context.server_name', 'server')),
                            now()->format('H:i d/m/Y'),
                        ),
                    ],
                ]],
            ])->throw();
    }

    /**
     * @return array<int, array{name:string,value:string,inline:bool}>
     */
    private function contextFields(): array
    {
        $context = config('services.discord.context', []);

        $items = [
            ['name' => 'App', 'value' => trim((string) Arr::get($context, 'app_name', '')), 'inline' => true],
            ['name' => 'Môi trường', 'value' => trim((string) Arr::get($context, 'app_env', '')), 'inline' => true],
            ['name' => 'Server', 'value' => trim((string) Arr::get($context, 'server_name', '')), 'inline' => true],
            ['name' => 'IP', 'value' => trim((string) Arr::get($context, 'server_ip', '')), 'inline' => true],
            ['name' => 'Role', 'value' => trim((string) Arr::get($context, 'server_role', '')), 'inline' => true],
            ['name' => 'Region', 'value' => trim((string) Arr::get($context, 'server_region', '')), 'inline' => true],
            ['name' => 'URL', 'value' => trim((string) Arr::get($context, 'app_url', '')), 'inline' => false],
        ];

        return collect($items)
            ->filter(fn (array $item): bool => $item['value'] !== '')
            ->values()
            ->all();
    }
}
