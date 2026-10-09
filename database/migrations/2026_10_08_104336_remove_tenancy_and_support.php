<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        foreach (['support_messages', 'support_conversations', 'tenant_settings'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("Cannot remove {$table}: existing data requires review.");
            }
        }

        if (Schema::hasTable('tenants') && (DB::table('tenants')->count() > 1 || DB::table('tenants')->where('is_main', false)->exists())) {
            throw new RuntimeException('Cannot remove tenancy while child websites exist.');
        }

        foreach (['username', 'email', 'phone'] as $column) {
            if (DB::table('users')->whereNotNull($column)->select($column)->groupBy($column)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException("Cannot remove tenancy: duplicate users.{$column} requires review.");
            }
        }

        $removedTables = ['support_messages', 'support_conversations', 'tenant_settings', 'tenant_domains', 'tenants'];
        $tables = DB::getDriverName() === 'sqlite' ? Schema::getTables() : Schema::getTables(DB::connection()->getDatabaseName());
        foreach ($tables as $table) {
            if (in_array($table['name'], $removedTables, true)) {
                continue;
            }
            foreach (Schema::getForeignKeys($table['name']) as $foreignKey) {
                if (in_array($foreignKey['foreign_table'], $removedTables, true)
                    && ! (in_array($table['name'], ['users', 'admin_audit_logs'], true) && $foreignKey['columns'] === ['tenant_id'])) {
                    throw new RuntimeException("Cannot remove tenancy/support: {$table['name']} still references {$foreignKey['foreign_table']}.");
                }
            }
        }

        foreach (['support_messages', 'support_conversations', 'tenant_settings', 'tenant_domains'] as $table) {
            Schema::dropIfExists($table);
        }

        foreach (['users', 'admin_audit_logs'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }
            foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
                if (in_array('tenant_id', $foreignKey['columns'], true)) {
                    Schema::table($tableName, function (Blueprint $table) use ($foreignKey): void {
                        $table->dropForeign(DB::getDriverName() === 'sqlite' ? $foreignKey['columns'] : $foreignKey['name']);
                    });
                }
            }
            foreach (Schema::getIndexes($tableName) as $index) {
                if (in_array('tenant_id', $index['columns'], true)) {
                    Schema::table($tableName, function (Blueprint $table) use ($index): void {
                        $index['unique'] ? $table->dropUnique($index['name']) : $table->dropIndex($index['name']);
                    });
                }
            }
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('tenant_id');
            });
        }

        Schema::dropIfExists('tenants');
        foreach (['username', 'email', 'phone'] as $column) {
            $hasUnique = collect(Schema::getIndexes('users'))->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === [$column]);
            if (! $hasUnique) {
                Schema::table('users', function (Blueprint $table) use ($column): void {
                    $table->unique($column);
                });
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Tenancy and support retirement is forward-only; restore from backup to reverse it.');
    }
};
