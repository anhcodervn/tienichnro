<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'game_seo_settings',
        'member_level_package_prices',
        'member_level_global_package_prices',
        'tenant_package_prices',
        'user_package_prices',
        'user_global_package_prices',
        'topup_provider_prices',
        'global_topup_package_game_settings',
        'topup_packages',
        'game_servers',
        'games',
        'global_topup_packages',
        'topup_providers',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("Cannot remove {$table}: archive its existing data before retiring the game catalog.");
            }
        }

        $foreignKeys = [];
        $tables = DB::connection()->getDriverName() === 'sqlite'
            ? Schema::getTables()
            : Schema::getTables(DB::connection()->getDatabaseName());
        foreach ($tables as $table) {
            if (in_array($table['name'], $this->tables, true)) {
                continue;
            }

            foreach (Schema::getForeignKeys($table['name']) as $foreignKey) {
                if (! in_array($foreignKey['foreign_table'], $this->tables, true)) {
                    continue;
                }

                if (! in_array($table['name'], ['orders', 'seo_posts'], true)) {
                    throw new RuntimeException("Cannot retire the game catalog: {$table['name']} still references {$foreignKey['foreign_table']}.");
                }

                $foreignKeys[$table['name']][] = DB::connection()->getDriverName() === 'sqlite'
                    ? $foreignKey['columns']
                    : $foreignKey['name'];
            }
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA defer_foreign_keys = ON');
        }

        foreach ($foreignKeys as $table => $names) {
            Schema::table($table, function (Blueprint $blueprint) use ($names): void {
                foreach ($names as $name) {
                    $blueprint->dropForeign($name);
                }
            });
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
        throw new RuntimeException('Game catalog retirement is irreversible. Restore its schema from a backup if required.');
    }
};
