<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Game extends Model
{
    use HasFactory;

    /** @var array<int, array{key:string,label:string,placeholder:string,required:bool,regex:string,type:string,options:array<int, array{value:string,text:string}>,min:float|null,max:float|null,step:float|null}> */
    public const DEFAULT_CHECKOUT_FIELDS = [
        ['key' => 'game_account', 'label' => 'Tài khoản game', 'placeholder' => 'Tài khoản đăng nhập game', 'required' => true, 'regex' => '', 'type' => 'text', 'options' => [], 'min' => null, 'max' => null, 'step' => null],
        ['key' => 'character_name', 'label' => 'Tên nhân vật', 'placeholder' => 'Không bắt buộc', 'required' => false, 'regex' => '', 'type' => 'text', 'options' => [], 'min' => null, 'max' => null, 'step' => null],
    ];

    protected $fillable = [
        'name', 'slug', 'short_name', 'reward_label', 'provider_service_code', 'image', 'description', 'content', 'status',
        'package_mode', 'min_quantity', 'max_quantity', 'sort_order', 'seo_title', 'seo_description', 'metadata', 'checkout_fields',
    ];

    protected $attributes = [
        'reward_label' => 'Thực nhận',
        'package_mode' => 'custom',
        'min_quantity' => 1,
        'max_quantity' => 10,
        'status' => 'active',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'checkout_fields' => 'array',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return array<int, array{key:string,label:string,placeholder:string,required:bool,regex:string,type:string,options:array<int, array{value:string,text:string}>,min:float|null,max:float|null,step:float|null}> */
    public function checkoutFields(): array
    {
        $fields = is_array($this->checkout_fields) && $this->checkout_fields !== []
            ? $this->checkout_fields
            : self::DEFAULT_CHECKOUT_FIELDS;

        $normalizedFields = collect($fields)
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null) && filled($field['label'] ?? null))
            ->map(function (array $field): array {
                $type = in_array($field['type'] ?? null, ['text', 'number', 'select'], true)
                    ? $field['type']
                    : 'text';
                $options = $type === 'select' && is_array($field['options'] ?? null)
                    ? collect($field['options'])
                        ->filter(fn (mixed $option): bool => is_array($option) && filled($option['value'] ?? null) && filled($option['text'] ?? null))
                        ->map(fn (array $option): array => [
                            'value' => (string) $option['value'],
                            'text' => (string) $option['text'],
                        ])
                        ->values()
                        ->all()
                    : [];

                return [
                    'key' => (string) $field['key'] === 'game_character' ? 'character_name' : (string) $field['key'],
                    'label' => (string) $field['label'],
                    'placeholder' => (string) ($field['placeholder'] ?? ''),
                    'required' => (bool) ($field['required'] ?? false),
                    'regex' => (string) ($field['regex'] ?? ''),
                    'type' => $type,
                    'options' => $options,
                    'min' => $type === 'number' && is_numeric($field['min'] ?? null) ? (float) $field['min'] : null,
                    'max' => $type === 'number' && is_numeric($field['max'] ?? null) ? (float) $field['max'] : null,
                    'step' => $type === 'number' && is_numeric($field['step'] ?? null) && (float) $field['step'] > 0
                        ? (float) $field['step']
                        : null,
                ];
            })
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

    public function seoSetting(): HasOne
    {
        return $this->hasOne(GameSeoSetting::class);
    }
}
