<?php

namespace Database\Factories;

use App\Models\NotificationSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<NotificationSubscription> */
class NotificationSubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'request_id' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'test'), 'mode' => 'webhook', 'status' => 'active', 'service_code' => 'notification-service', 'package_name' => 'Webhook', 'price' => 50000, 'billing_type' => 'time', 'starts_at' => now(), 'expires_at' => now()->addMonth(), 'webhook_url' => 'https://8.8.8.8/webhook', 'webhook_secret' => Str::random(64)];
    }
}
