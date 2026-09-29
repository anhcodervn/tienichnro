<?php

namespace App\Models;

use App\Enums\TaxCalculationType;
use Database\Factories\GameServiceOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class GameServiceOrder extends Model
{
    /** @use HasFactory<GameServiceOrderFactory> */
    use HasFactory;

    public const STATUSES = ['pending', 'processing', 'review', 'completed', 'failed', 'cancelled'];

    protected $fillable = [
        'code', 'user_id', 'collaborator_id', 'game_id', 'game_service_id', 'game_service_package_id', 'game_service_package_price_id', 'game_server_id',
        'email', 'game_name', 'service_name', 'package_name', 'price_label', 'server_name', 'payload', 'quantity', 'unit_price',
        'total_amount', 'collaborator_unit_cost', 'collaborator_total_cost', 'gross_profit', 'tax_enabled',
        'collaborator_settlement_amount', 'collaborator_wallet_transaction_id', 'collaborator_settled_at',
        'tax_calculation_type', 'vat_rate', 'pit_rate', 'estimated_vat', 'estimated_pit', 'estimated_tax', 'net_profit',
        'profit_margin', 'status', 'admin_note', 'processing_at', 'completed_at',
    ];

    protected $attributes = ['status' => 'pending', 'quantity' => 1];

    protected function casts(): array
    {
        return [
            'payload' => 'array', 'quantity' => 'integer', 'unit_price' => 'integer', 'total_amount' => 'integer',
            'collaborator_unit_cost' => 'integer', 'collaborator_total_cost' => 'integer', 'gross_profit' => 'integer',
            'tax_enabled' => 'boolean', 'tax_calculation_type' => TaxCalculationType::class,
            'vat_rate' => 'decimal:4', 'pit_rate' => 'decimal:4', 'estimated_vat' => 'integer', 'estimated_pit' => 'integer',
            'estimated_tax' => 'integer', 'net_profit' => 'integer', 'profit_margin' => 'decimal:4',
            'processing_at' => 'datetime', 'completed_at' => 'datetime',
            'collaborator_settled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->code ??= self::generateCode();
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = 'GSV'.now()->format('ymd').Str::upper(Str::random(6));
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

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collaborator_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GameServiceOrderMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(GameServiceOrderMessage::class)->latestOfMany();
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GameService::class, 'game_service_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(GameServicePackage::class, 'game_service_package_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(GameServicePackagePrice::class, 'game_service_package_price_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(GameServer::class, 'game_server_id');
    }
}
