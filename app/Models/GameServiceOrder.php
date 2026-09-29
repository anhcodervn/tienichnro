<?php

namespace App\Models;

use Database\Factories\GameServiceOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GameServiceOrder extends Model
{
    /** @use HasFactory<GameServiceOrderFactory> */
    use HasFactory;

    public const STATUSES = ['pending', 'processing', 'completed', 'failed', 'cancelled'];

    protected $fillable = [
        'code', 'user_id', 'game_id', 'game_service_id', 'game_service_package_id', 'game_service_package_price_id', 'game_server_id',
        'email', 'game_name', 'service_name', 'package_name', 'price_label', 'server_name', 'payload', 'quantity', 'unit_price',
        'total_amount', 'status', 'admin_note', 'processing_at', 'completed_at',
    ];

    protected $attributes = ['status' => 'pending', 'quantity' => 1];

    protected function casts(): array
    {
        return [
            'payload' => 'array', 'quantity' => 'integer', 'unit_price' => 'integer', 'total_amount' => 'integer',
            'processing_at' => 'datetime', 'completed_at' => 'datetime',
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
