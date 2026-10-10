<?php

namespace Database\Factories;

use App\Models\NotificationSubscription;
use App\Models\NotificationWebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationWebhookDelivery> */
class NotificationWebhookDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return ['subscription_id' => NotificationSubscription::factory(), 'event_key' => fake()->sha256(), 'payload' => ['event' => 'game.notification', 'content' => 'Test notification'], 'status' => 'pending'];
    }
}
