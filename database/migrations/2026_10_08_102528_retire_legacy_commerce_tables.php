<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'api_logs', 'api_keys', 'coupon_logs', 'coupons',
        'member_level_order_credits', 'member_level_histories', 'member_level_accounts',
        'order_recipients', 'payment_transactions', 'orders',
        'wallet_transactions', 'wallets', 'recharge_bonus_tiers', 'config_recharge', 'member_levels',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if ($table === 'member_levels') {
                if (DB::table($table)->whereNotIn('code', ['member', 'level-1', 'level-2', 'level-3'])->exists()) {
                    throw new RuntimeException('Cannot remove custom member levels: archive their definitions before retiring commerce.');
                }

                continue;
            }

            if (DB::table($table)->exists()) {
                throw new RuntimeException("Cannot remove {$table}: archive its existing data before retiring commerce.");
            }
        }

        $tables = DB::connection()->getDriverName() === 'sqlite'
            ? Schema::getTables()
            : Schema::getTables(DB::connection()->getDatabaseName());
        foreach ($tables as $table) {
            if (in_array($table['name'], $this->tables, true)) {
                continue;
            }

            foreach (Schema::getForeignKeys($table['name']) as $foreignKey) {
                if (in_array($foreignKey['foreign_table'], $this->tables, true)) {
                    throw new RuntimeException("Cannot retire commerce: {$table['name']} still references {$foreignKey['foreign_table']}.");
                }
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
        throw new RuntimeException('Commerce retirement is irreversible. Restore the previous schema from a backup if required.');
    }
};
