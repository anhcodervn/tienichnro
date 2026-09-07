<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    /** @var array<int, array{key:string,label:string,placeholder:string,required:bool,regex:string}> */
    public const DEFAULT_CHECKOUT_FIELDS = [
        ['key' => 'game_account', 'label' => 'Tài khoản game', 'placeholder' => 'Tài khoản đăng nhập game', 'required' => true, 'regex' => ''],
        ['key' => 'game_character', 'label' => 'Tên nhân vật', 'placeholder' => 'Không bắt buộc', 'required' => false, 'regex' => ''],
    ];

    protected $fillable = [
        'name', 'slug', 'short_name', 'reward_label', 'provider_service_code', 'image', 'description', 'content', 'status',
        'package_mode', 'sort_order', 'seo_title', 'seo_description', 'metadata', 'checkout_fields',
    ];

    protected $attributes = ['reward_label' => 'Thực nhận', 'package_mode' => 'custom', 'status' => 'active', 'sort_order' => 0];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'checkout_fields' => 'array', 'sort_order' => 'integer'];
    }

    /** @return array<int, array{key:string,label:string,placeholder:string,required:bool,regex:string}> */
    public function checkoutFields(): array
    {
        $fields = is_array($this->checkout_fields) && $this->checkout_fields !== []
            ? $this->checkout_fields
            : self::DEFAULT_CHECKOUT_FIELDS;

        $normalizedFields = collect($fields)
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null) && filled($field['label'] ?? null))
            ->map(fn (array $field): array => [
                'key' => (string) $field['key'],
                'label' => (string) $field['label'],
                'placeholder' => (string) ($field['placeholder'] ?? ''),
                'required' => (bool) ($field['required'] ?? false),
                'regex' => (string) ($field['regex'] ?? ''),
            ])
            ->values()
            ->all();

        return $normalizedFields !== [] ? $normalizedFields : self::DEFAULT_CHECKOUT_FIELDS;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function servers(): HasMany
    {
        return $this->hasMany(GameServer::class)->orderBy('sort_order')->orderBy('id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TopupPackage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function globalPackageSettings(): HasMany
    {
        return $this->hasMany(GlobalTopupPackageGameSetting::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
