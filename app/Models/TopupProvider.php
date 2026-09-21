<?php

namespace App\Models;

use App\Enums\TopupProviderType;
use Database\Factories\TopupProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TopupProvider extends Model
{
    public const SECRET_MASK = '********';

    /** @use HasFactory<TopupProviderFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'connection_config',
        'balance',
        'balance_currency',
        'balance_status',
        'balance_checked_at',
        'balance_error_code',
        'balance_error_message',
    ];

    protected $hidden = [
        'connection_config',
    ];

    protected function casts(): array
    {
        return [
            'type' => TopupProviderType::class,
            'connection_config' => 'encrypted:array',
            'balance' => 'integer',
            'balance_checked_at' => 'datetime',
        ];
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TopupPackage::class, 'provider_id');
    }

    public function globalPackages(): HasMany
    {
        return $this->hasMany(GlobalTopupPackage::class, 'provider_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'topup_provider_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(TopupProviderPrice::class, 'topup_provider_id');
    }

    /** @return array<string, mixed> */
    public function maskedConnectionConfig(): array
    {
        return $this->maskSecrets($this->connection_config ?? []);
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     * @return array<string, mixed>
     */
    public function mergeMaskedConnectionConfig(array $connectionConfig): array
    {
        return $this->mergeMaskedValues($connectionConfig, $this->connection_config ?? []);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function maskSecrets(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->maskSecrets($value);
            } elseif ($this->isSensitiveKey((string) $key) && filled($value)) {
                $values[$key] = self::SECRET_MASK;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function mergeMaskedValues(array $incoming, array $existing): array
    {
        foreach ($incoming as $key => $value) {
            if ($value === self::SECRET_MASK && array_key_exists($key, $existing)) {
                $incoming[$key] = $existing[$key];
            } elseif (is_array($value) && is_array($existing[$key] ?? null)) {
                $incoming[$key] = $this->mergeMaskedValues($value, $existing[$key]);
            }
        }

        return $incoming;
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/secret|serect|token|password|api[_-]?key|partner[_-]?key|authorization|private[_-]?key/i', $key) === 1;
    }
}
