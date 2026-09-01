<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('member_levels')->insertOrIgnore([
            ['code' => 'member', 'name' => 'Thành viên', 'rank' => 0, 'lifetime_threshold' => 0, 'maintenance_amount' => 0, 'maintenance_days' => 31, 'default_discount_bps' => 0, 'minimum_profit' => 0, 'color' => '#64748b', 'icon' => 'user', 'status' => 'active', 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'level-1', 'name' => 'Level 1', 'rank' => 1, 'lifetime_threshold' => 100000, 'maintenance_amount' => 10000, 'maintenance_days' => 31, 'default_discount_bps' => 0, 'minimum_profit' => 0, 'color' => '#0f766e', 'icon' => 'badge', 'status' => 'active', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'level-2', 'name' => 'Level 2', 'rank' => 2, 'lifetime_threshold' => 1000000, 'maintenance_amount' => 100000, 'maintenance_days' => 31, 'default_discount_bps' => 0, 'minimum_profit' => 0, 'color' => '#2563eb', 'icon' => 'medal', 'status' => 'active', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'level-3', 'name' => 'Level 3', 'rank' => 3, 'lifetime_threshold' => 1500000, 'maintenance_amount' => 300000, 'maintenance_days' => 31, 'default_discount_bps' => 0, 'minimum_profit' => 0, 'color' => '#7c3aed', 'icon' => 'crown', 'status' => 'active', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('member_levels')->whereIn('code', ['member', 'level-1', 'level-2', 'level-3'])->delete();
    }
};
