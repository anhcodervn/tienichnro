<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    use BelongsToTenant, HasFactory;

    protected $hidden = [
        'topup_provider_id',
        'provider',
        'provider_reference',
        'metadata',
        'normalized_email',
        'customer_ip',
        'user_agent',
        'provider_unit_cost',
        'provider_total_cost',
        'gross_profit',
    ];

    protected $fillable = [
        'tenant_id', 'code', 'idempotency_key', 'user_id', 'billing_user_id', 'email', 'normalized_email', 'game_id',
        'game_server_id', 'topup_package_id', 'package_source', 'global_topup_package_id',
        'global_topup_package_name', 'topup_provider_id', 'member_level_id', 'member_level_name',
        'member_level_pricing_mode', 'member_level_discount_bps', 'purchase_mode', 'checkout_fields_snapshot',
        'game_account', 'game_character', 'quantity',
        'package_name', 'denomination', 'carot_amount', 'unit_price', 'sale_unit_price', 'retail_unit_price',
        'tenant_cost_unit_price', 'tenant_cost_total', 'tenant_profit', 'subtotal',
        'discount_amount', 'member_level_discount_amount', 'total_amount', 'provider_unit_cost', 'provider_total_cost', 'gross_profit',
        'payment_method', 'payment_status', 'order_status',
        'provider_reference', 'paid_at', 'processing_at', 'completed_at', 'failed_at',
        'cancelled_at', 'failure_reason', 'customer_ip', 'user_agent', 'metadata',
    ];

    protected $attributes = [
        'package_source' => 'custom',
        'payment_status' => PaymentStatus::Pending->value,
        'order_status' => OrderStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'order_status' => OrderStatus::class,
            'quantity' => 'integer', 'denomination' => 'integer', 'carot_amount' => 'integer',
            'member_level_discount_bps' => 'integer',
            'checkout_fields_snapshot' => 'array',
            'unit_price' => 'decimal:2', 'sale_unit_price' => 'decimal:2', 'retail_unit_price' => 'decimal:2', 'subtotal' => 'decimal:2',
            'tenant_cost_unit_price' => 'integer', 'tenant_cost_total' => 'integer', 'tenant_profit' => 'integer',
            'discount_amount' => 'decimal:2', 'member_level_discount_amount' => 'decimal:2', 'total_amount' => 'decimal:2',
            'provider_unit_cost' => 'decimal:2', 'provider_total_cost' => 'decimal:2', 'gross_profit' => 'decimal:2',
            'paid_at' => 'datetime', 'processing_at' => 'datetime', 'completed_at' => 'datetime',
            'failed_at' => 'datetime', 'cancelled_at' => 'datetime', 'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->code ??= self::generateCode();
            $order->idempotency_key ??= (string) Str::uuid();
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = 'TOP'.now()->format('ymd').Str::upper(Str::random(6));
        } while (self::query()->withoutGlobalScope(TenantScope::class)->where('code', $code)->exists());

        return $code;
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'billing_user_id')->withoutGlobalScope(TenantScope::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(GameServer::class, 'game_server_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TopupPackage::class, 'topup_package_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TopupProvider::class, 'topup_provider_id');
    }

    public function globalTopupPackage(): BelongsTo
    {
        return $this->belongsTo(GlobalTopupPackage::class);
    }

    public function memberLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function latestPaymentTransaction(): HasOne
    {
        return $this->hasOne(PaymentTransaction::class)->latestOfMany();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(OrderRecipient::class)->orderBy('position');
    }

    public function memberLevelCredit(): HasOne
    {
        return $this->hasOne(MemberLevelOrderCredit::class);
    }

    public function affiliateCommission(): HasOne
    {
        return $this->hasOne(AffiliateCommission::class);
    }
}
