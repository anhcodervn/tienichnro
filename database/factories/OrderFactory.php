<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\TopupPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'TOP'.now()->format('ymd').Str::upper(Str::random(6)),
            'idempotency_key' => (string) Str::uuid(),
            'user_id' => null,
            'email' => fake()->safeEmail(),
            'normalized_email' => fn (array $attributes): string => Str::lower($attributes['email']),
            'game_id' => Game::factory(),
            'game_server_id' => null,
            'topup_package_id' => TopupPackage::factory(),
            'purchase_mode' => 'single',
            'checkout_fields_snapshot' => Game::DEFAULT_CHECKOUT_FIELDS,
            'game_account' => fake()->userName(),
            'game_character' => null,
            'quantity' => 1,
            'package_name' => '500 Carot',
            'carot_amount' => 500,
            'unit_price' => 500000,
            'subtotal' => 500000,
            'discount_amount' => 50000,
            'total_amount' => 450000,
            'payment_method' => PaymentMethod::BankTransfer,
            'payment_status' => PaymentStatus::Pending,
            'order_status' => OrderStatus::Pending,
            'metadata' => [],
        ];
    }

    public function forServer(GameServer $server): static
    {
        return $this->state(fn (): array => ['game_id' => $server->game_id, 'game_server_id' => $server->id]);
    }
}
