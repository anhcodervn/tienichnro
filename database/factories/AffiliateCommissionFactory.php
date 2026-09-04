<?php

namespace Database\Factories;

use App\Models\AffiliateCommission;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TopupPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateCommission>
 */
class AffiliateCommissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'order_id' => Order::factory(),
            'referrer_id' => User::factory(),
            'referred_user_id' => User::factory(),
            'topup_package_id' => TopupPackage::factory(),
            'commission_type' => 'fixed',
            'rate_value' => 1000,
            'base_amount' => 100000,
            'quantity' => 1,
            'amount' => 1000,
            'holding_days' => 7,
            'status' => AffiliateCommission::STATUS_PENDING,
            'is_flagged' => false,
        ];
    }
}
