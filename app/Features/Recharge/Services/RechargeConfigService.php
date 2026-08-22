<?php

namespace App\Features\Recharge\Services;

use App\Models\ConfigRecharge;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class RechargeConfigService
{
    public function __construct(private readonly BankTransferContentService $bankTransferContentService) {}

    /**
     * @return array<string, string>
     */
    public function placeholderExamples(): array
    {
        return [
            '{bank_name}' => 'MB',
            '{account_name}' => 'CONG TY AUTOCRON',
            '{account_number}' => '123456789',
            '{amount}' => '500000',
            '{user_id}' => '123',
            '{prefix}' => 'NOIDUNG',
            '{nd}' => 'NOIDUNGABC12345',
        ];
    }

    public function current(): ?ConfigRecharge
    {
        return ConfigRecharge::query()
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, ConfigRecharge>
     */
    public function all(): Collection
    {
        return ConfigRecharge::query()
            ->orderByDesc('is_active')
            ->latest('id')
            ->get();
    }

    /**
     * @return Collection<int, ConfigRecharge>
     */
    public function active(): Collection
    {
        return ConfigRecharge::query()
            ->where('is_active', true)
            ->latest('id')
            ->get();
    }

    public function isApiBankVnProvider(?ConfigRecharge $config): bool
    {
        return $config instanceof ConfigRecharge && $config->provider === 'apibankvn_api';
    }

    public function create(array $payload): ConfigRecharge
    {
        /** @var ConfigRecharge $config */
        $config = ConfigRecharge::query()->create($payload);

        return $config->refresh();
    }

    public function update(ConfigRecharge $config, array $payload): ConfigRecharge
    {
        $config->fill($payload);
        $config->save();

        return $config->refresh();
    }

    public function toggle(ConfigRecharge $config): ConfigRecharge
    {
        $config->forceFill([
            'is_active' => ! $config->is_active,
        ])->save();

        return $config->refresh();
    }

    public function delete(ConfigRecharge $config): void
    {
        $config->delete();
    }

    public function resolveActiveById(?int $configId): ?ConfigRecharge
    {
        if ($configId !== null) {
            return ConfigRecharge::query()
                ->whereKey($configId)
                ->where('is_active', true)
                ->first();
        }

        return $this->current();
    }

    public function resolveById(?int $configId): ?ConfigRecharge
    {
        if ($configId === null) {
            return null;
        }

        return ConfigRecharge::query()->whereKey($configId)->first();
    }

    public function transferContentFor(User $user, ConfigRecharge $config): string
    {
        return $this->buildTransferContent(
            prefix: (string) $config->transfer_prefix,
            userId: $user->id,
        );
    }

    public function buildQrUrl(ConfigRecharge $config, User $user, float|int|string $amount): string
    {
        return $this->buildQrUrlForTransfer(
            config: $config,
            amount: $amount,
            userId: $user->id,
            transferContent: $this->transferContentFor($user, $config),
        );
    }

    public function buildQrUrlForTransfer(ConfigRecharge $config, float|int|string $amount, int|string $userId, string $transferContent): string
    {
        return $this->replaceTemplate(
            template: (string) $config->qr_template,
            replacements: [
                'bank_code' => (string) $config->bank_name,
                'bank_name' => (string) $config->bank_name,
                'account_name' => (string) $config->account_name,
                'account_number' => (string) $config->account_number,
                'amount' => (string) (int) round((float) $amount),
                'user_id' => (string) $userId,
                'prefix' => $this->normalizePrefix((string) $config->transfer_prefix),
                'nd' => $transferContent,
            ],
        );
    }

    public function previewQrUrl(ConfigRecharge $config, float|int|string $amount = 500000): string
    {
        return $this->replaceTemplate(
            template: (string) $config->qr_template,
            replacements: [
                'bank_code' => (string) $config->bank_name,
                'bank_name' => (string) $config->bank_name,
                'account_name' => (string) $config->account_name,
                'account_number' => (string) $config->account_number,
                'amount' => (string) (int) round((float) $amount),
                'user_id' => '123',
                'prefix' => $this->normalizePrefix((string) $config->transfer_prefix),
                'nd' => $this->previewTransferContent((string) $config->transfer_prefix, 123),
            ],
        );
    }

    public function previewTransferContent(string $prefix, int|string $userId = 123): string
    {
        return $this->bankTransferContentService->preview($prefix);
    }

    public function normalizePrefix(string $prefix): string
    {
        return $this->bankTransferContentService->normalizePrefix($prefix);
    }

    public function buildTransferContent(string $prefix, int|string $userId): string
    {
        return $this->bankTransferContentService->fromReference($prefix, $userId);
    }

    /**
     * @param  array<string, scalar|null>  $replacements
     */
    public function replaceTemplate(string $template, array $replacements): string
    {
        $map = [];

        foreach ($replacements as $key => $value) {
            $map['{'.$key.'}'] = rawurlencode((string) $value);
        }

        return strtr($template, $map);
    }

    public function hasUnresolvedPlaceholders(string $value): bool
    {
        return preg_match('/\{[A-Za-z0-9_]+\}/', $value) === 1;
    }
}
