<?php

namespace Database\Seeders;

use App\Models\MemberLevel;
use Illuminate\Database\Seeder;

class MemberLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            ['code' => 'member', 'name' => 'Thành viên', 'rank' => 0, 'lifetime_threshold' => 0, 'maintenance_amount' => 0, 'color' => '#64748b', 'icon' => 'user'],
            ['code' => 'level-1', 'name' => 'Level 1', 'rank' => 1, 'lifetime_threshold' => 100000, 'maintenance_amount' => 10000, 'color' => '#0f766e', 'icon' => 'badge'],
            ['code' => 'level-2', 'name' => 'Level 2', 'rank' => 2, 'lifetime_threshold' => 1000000, 'maintenance_amount' => 100000, 'color' => '#2563eb', 'icon' => 'medal'],
            ['code' => 'level-3', 'name' => 'Level 3', 'rank' => 3, 'lifetime_threshold' => 1500000, 'maintenance_amount' => 300000, 'color' => '#7c3aed', 'icon' => 'crown'],
        ];

        foreach ($levels as $level) {
            MemberLevel::query()->updateOrCreate(['code' => $level['code']], [
                ...$level,
                'maintenance_days' => 31,
                'default_discount_bps' => 0,
                'minimum_profit' => 0,
                'status' => 'active',
                'sort_order' => $level['rank'],
            ]);
        }
    }
}
