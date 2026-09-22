<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TopupPackage extends Model
{
    use HasFactory;

    protected $hidden = [
        'provider_id',
        'provider',
        'provider_service_code',
        'provider_price',
        'metadata',
    ];

    protected $fillable = [
        'game_id', 'game_server_id', 'global_topup_package_id', 'provider_id', 'provider_service_code', 'name', 'denomination', 'carot_amount',
        'reward_x2_amount', 'reward_x3_amount', 'first_topup_reward_amount', 'provider_price',
        'price', 'original_price', 'description', 'bonus_text', 'status', 'sort_order', 'metadata',
    ];

    protected $attributes = ['status' => 'active', 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'denomination' => 'integer', 'carot_amount' => 'integer',
            'reward_x2_amount' => 'integer', 'reward_x3_amount' => 'integer', 'first_topup_reward_amount' => 'integer',
            'provider_price' => 'decimal:2', 'price' => 'decimal:2',
            'original_price' => 'decimal:2', 'discount_percent' => 'decimal:2',
            'sort_order' => 'integer', 'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $package): void {
            $package->discount_percent = $package->calculateDiscountPercent();
        });
    }

    public function calculateDiscountPercent(): string
    {
        $originalPrice = (int) ($this->original_price ?? 0);
        $salePrice = (int) ($this->price ?? 0);

        if ($originalPrice <= 0 || $originalPrice < $salePrice) {
            return '0.00';
        }

        $discountBasisPoints = intdiv(
            (($originalPrice - $salePrice) * 10000) + intdiv($originalPrice, 2),
            $originalPrice,
        );

        return sprintf('%d.%02d', intdiv($discountBasisPoints, 100), $discountBasisPoints % 100);
    }

    public function providerServiceCode(): ?string
    {
        $serviceCode = trim((string) $this->game?->provider_service_code);

        return $serviceCode !== '' ? $serviceCode : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function rewardItems(?string $fallbackLabel = null): array
    {
        $items = data_get($this->metadata, 'global_receives');

        if (is_array($items) && $items !== []) {
            return array_values($items);
        }

        if ($this->carot_amount === null) {
            return [];
        }

        return [[
            'code' => '',
            'label' => $fallbackLabel ?: 'Thực nhận',
            'base_amount' => $this->carot_amount,
            'reward_x2_amount' => $this->reward_x2_amount,
            'reward_x3_amount' => $this->reward_x3_amount,
            'first_topup_reward_amount' => $this->first_topup_reward_amount,
        ]];
    }

    public function rewardLabel(?string $fallbackLabel = null): string
    {
        $items = $this->rewardItems($fallbackLabel);

        if (count($items) === 1) {
            return (string) data_get($items, '0.label', $fallbackLabel ?: 'Thực nhận');
        }

        return collect($items)
            ->map(fn (array $item): string => (string) ($item['code'] ?: $item['label']))
            ->implode(' | ');
    }

    public function rewardAmounts(string $field = 'base_amount', int $quantity = 1, ?string $fallbackLabel = null): ?string
    {
        $amounts = collect($this->rewardItems($fallbackLabel))
            ->map(fn (array $item): mixed => $item[$field] ?? null)
            ->filter(fn (mixed $amount): bool => $amount !== null)
            ->map(fn (mixed $amount): string => number_format((int) $amount * $quantity, 0, ',', '.'));

        return $amounts->isEmpty() ? null : $amounts->implode(' | ');
    }

    public function rewardDisplay(string $field = 'base_amount', int $quantity = 1, ?string $fallbackLabel = null): ?string
    {
        $items = collect($this->rewardItems($fallbackLabel));
        $isMultiple = $items->count() > 1;
        $display = $items
            ->filter(fn (array $item): bool => ($item[$field] ?? null) !== null)
            ->map(function (array $item) use ($field, $quantity, $isMultiple): string {
                $unit = $isMultiple ? ($item['code'] ?: $item['label']) : $item['label'];

                return number_format((int) $item[$field] * $quantity, 0, ',', '.').' '.$unit;
            });

        return $display->isEmpty() ? null : $display->implode(' | ');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(GameServer::class, 'game_server_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TopupProvider::class, 'provider_id');
    }

    public function globalTopupPackage(): BelongsTo
    {
        return $this->belongsTo(GlobalTopupPackage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function providerPrices(): HasMany
    {
        return $this->hasMany(TopupProviderPrice::class, 'topup_package_id');
    }

    public function memberLevelPrices(): HasMany
    {
        return $this->hasMany(MemberLevelPackagePrice::class);
    }
}
