<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'affiliate_announcement_reads',
        'affiliate_announcements',
        'affiliate_commissions',
        'affiliate_conversions',
        'affiliate_global_package_rates',
        'affiliate_package_rates',
        'affiliate_profiles',
        'affiliate_programs',
        'affiliate_withdrawals',
        'collaborator_game_service_permissions',
        'game_server_game_service',
        'game_service_order_messages',
        'game_service_order_progress',
        'game_service_orders',
        'game_service_package_prices',
        'game_service_packages',
        'game_services',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("Cannot remove {$table}: archive its existing data before retiring affiliate and collaborator tables.");
            }
        }

        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Affiliate and collaborator retirement is irreversible. Restore the previous schema from a backup if required.');
    }
};
