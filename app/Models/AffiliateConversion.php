<?php

namespace App\Models;

use App\Features\Affiliate\Events\AffiliateDashboardUpdated;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AffiliateConversionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateConversion extends Model
{
    /** @use HasFactory<AffiliateConversionFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'user_id', 'amount', 'idempotency_key', 'status'];

    protected $attributes = ['status' => 'completed'];

    protected static function booted(): void
    {
        static::saved(fn (self $conversion): mixed => AffiliateDashboardUpdated::dispatch((int) $conversion->user_id));
    }

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
