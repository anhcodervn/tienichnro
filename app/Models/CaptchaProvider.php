<?php

namespace App\Models;

use Database\Factories\CaptchaProviderFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaptchaProvider extends Model
{
    /** @use HasFactory<CaptchaProviderFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'driver', 'endpoint', 'api_key', 'priority', 'timeout', 'is_enabled'];

    protected $hidden = ['api_key'];

    protected $appends = ['has_api_key'];

    protected $attributes = ['priority' => 10, 'timeout' => 15, 'is_enabled' => false];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'priority' => 'integer', 'timeout' => 'integer', 'is_enabled' => 'boolean'];
    }

    /** @return Attribute<bool, never> */
    protected function hasApiKey(): Attribute
    {
        return Attribute::get(fn (): bool => filled($this->getRawOriginal('api_key')));
    }
}
