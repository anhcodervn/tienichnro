<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $hidden = [
        'topup_provider_id',
        'provider',
        'provider_reference',
        'metadata',
        'normalized_email',
        'customer_ip',
        'user_agent',
    ];

    protected $fillable = [
        'code', 'idempotency_key', 'user_id', 'email', 'normalized_email', 'game_id',
        'game_server_id', 'topup_package_id', 'topup_provider_id', 'purchase_mode', 'checkout_fields_snapshot',
        'game_account', 'game_character', 'quantity',
        'package_name', 'denomination', 'carot_amount', 'unit_price', 'subtotal',
        'discount_amount', 'total_amount', 'payment_method', 'payment_status', 'order_status',
        'provider_reference', 'paid_at', 'processing_at', 'completed_at', 'failed_at',
        'cancelled_at', 'failure_reason', 'customer_ip', 'user_agent', 'metadata',
    ];

    protected $attributes = [
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
            'checkout_fields_snapshot' => 'array',
            'unit_price' => 'decimal:2', 'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2', 'total_amount' => 'decimal:2',
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
        } while (self::query()->where('code', $code)->exists());

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

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(OrderRecipient::class)->orderBy('position');
    }
}
