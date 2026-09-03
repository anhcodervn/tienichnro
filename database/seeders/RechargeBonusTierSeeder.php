<?php

namespace Database\Seeders;

use App\Models\RechargeBonusTier;
use Illuminate\Database\Seeder;

class RechargeBonusTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([100_000 => 200, 500_000 => 500, 1_000_000 => 1_000] as $minimumAmount => $bonusBasisPoints) {
            RechargeBonusTier::query()->updateOrCreate(
                ['minimum_amount' => $minimumAmount],
                ['bonus_basis_points' => $bonusBasisPoints, 'is_active' => false],
            );
        }
    }
}
