<?php

namespace App\Models;

use Database\Factories\NotificationSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NotificationSubscription extends Model
{
    /** @use HasFactory<NotificationSubscriptionFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'service_package_id', 'wallet_transaction_id', 'request_id', 'request_hash', 'mode', 'status', 'service_code', 'package_name', 'price', 'billing_type', 'starts_at', 'expires_at', 'remaining_uses', 'zalo_id', 'characters', 'notification_types', 'webhook_url', 'webhook_secret'];

    protected $hidden = ['webhook_secret', 'request_hash', 'service_payload'];

    protected function casts(): array
    {
        return ['characters' => 'array', 'notification_types' => 'array', 'webhook_secret' => 'encrypted', 'service_payload' => 'encrypted:array',
            'user_id' => 'integer', 'price' => 'integer', 'remaining_uses' => 'integer', 'starts_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    public function hasAccess(): bool
    {
        return $this->status === 'active' && ($this->expires_at === null || $this->expires_at->isFuture())
            && ($this->billing_type !== 'usage' || $this->remaining_uses > 0);
    }

    public function latestDelivery(): HasOne
    {
        return $this->hasOne(NotificationWebhookDelivery::class, 'subscription_id')->latestOfMany();
    }
}
