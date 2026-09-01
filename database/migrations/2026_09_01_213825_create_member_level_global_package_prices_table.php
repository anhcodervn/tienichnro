<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'member_level_global_package_prices';

    private const MEMBER_LEVEL_FOREIGN = 'ml_global_prices_level_fk';

    private const GLOBAL_PACKAGE_FOREIGN = 'ml_global_prices_package_fk';

    private const LEVEL_PACKAGE_UNIQUE = 'member_level_global_package_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('member_level_id');
                $table->foreignId('global_topup_package_id');
                $table->string('pricing_mode', 20)->default('discount');
                $table->unsignedInteger('discount_basis_points')->nullable();
                $table->unsignedBigInteger('fixed_price')->nullable();
                $table->unsignedBigInteger('minimum_profit')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $needsMemberLevelForeign = ! $this->hasForeignKeyFor('member_level_id');
        $needsGlobalPackageForeign = ! $this->hasForeignKeyFor('global_topup_package_id');
        $needsUniqueIndex = ! $this->hasUniqueIndexFor(['member_level_id', 'global_topup_package_id']);

        Schema::table(self::TABLE, function (Blueprint $table) use ($needsMemberLevelForeign, $needsGlobalPackageForeign, $needsUniqueIndex): void {
            if ($needsMemberLevelForeign) {
                $table->foreign('member_level_id', self::MEMBER_LEVEL_FOREIGN)
                    ->references('id')
                    ->on('member_levels')
                    ->cascadeOnDelete();
            }

            if ($needsGlobalPackageForeign) {
                $table->foreign('global_topup_package_id', self::GLOBAL_PACKAGE_FOREIGN)
                    ->references('id')
                    ->on('global_topup_packages')
                    ->cascadeOnDelete();
            }

            if ($needsUniqueIndex) {
                $table->unique(['member_level_id', 'global_topup_package_id'], self::LEVEL_PACKAGE_UNIQUE);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    private function hasForeignKeyFor(string $column): bool
    {
        foreach (Schema::getForeignKeys(self::TABLE) as $foreignKey) {
            if (($foreignKey['columns'] ?? []) === [$column]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function hasUniqueIndexFor(array $columns): bool
    {
        foreach (Schema::getIndexes(self::TABLE) as $index) {
            if (($index['unique'] ?? false) && ($index['columns'] ?? []) === $columns) {
                return true;
            }
        }

        return false;
    }
};
