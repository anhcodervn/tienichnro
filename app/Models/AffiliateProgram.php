<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AffiliateProgramFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateProgram extends Model
{
    /** @use HasFactory<AffiliateProgramFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'is_enabled', 'minimum_withdrawal'];

    protected $attributes = ['is_enabled' => false, 'minimum_withdrawal' => 100000];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'minimum_withdrawal' => 'integer'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(AffiliatePackageRate::class, 'tenant_id', 'tenant_id');
    }

    public function globalRates(): HasMany
    {
        return $this->hasMany(AffiliateGlobalPackageRate::class, 'tenant_id', 'tenant_id');
    }
}
