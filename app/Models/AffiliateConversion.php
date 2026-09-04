<?php

namespace App\Models;

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

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
