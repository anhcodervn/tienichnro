<?php

namespace App\Models;

use App\Features\Affiliate\Events\AffiliateDashboardUpdated;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AffiliateProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateProfile extends Model
{
    /** @use HasFactory<AffiliateProfileFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'status', 'bank_name', 'bank_account_name', 'bank_account_number', 'admin_note',
    ];

    protected $hidden = ['bank_account_name', 'bank_account_number'];

    protected $attributes = ['status' => 'active'];

    protected static function booted(): void
    {
        static::saved(fn (self $profile): mixed => AffiliateDashboardUpdated::dispatch((int) $profile->user_id));
    }

    protected function casts(): array
    {
        return ['bank_account_name' => 'encrypted', 'bank_account_number' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
