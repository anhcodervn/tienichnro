<?php

namespace App\Models;

use Database\Factories\NotificationWebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationWebhookDelivery extends Model
{
    /** @use HasFactory<NotificationWebhookDeliveryFactory> */
    use HasFactory;

    protected $fillable = ['subscription_id', 'event_key', 'payload', 'status', 'attempts', 'quota_reserved', 'response_status', 'last_error', 'delivered_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'quota_reserved' => 'boolean', 'attempts' => 'integer', 'delivered_at' => 'immutable_datetime'];
    }
}
