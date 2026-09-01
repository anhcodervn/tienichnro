<?php

test('global level price migration can recover a partially created mysql table', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_09_01_213825_create_member_level_global_package_prices_table.php');

    expect($migration)
        ->toContain('if (! Schema::hasTable(self::TABLE))')
        ->toContain("private const MEMBER_LEVEL_FOREIGN = 'ml_global_prices_level_fk';")
        ->toContain("private const GLOBAL_PACKAGE_FOREIGN = 'ml_global_prices_package_fk';")
        ->toContain("! \$this->hasForeignKeyFor('global_topup_package_id')")
        ->toContain("! \$this->hasUniqueIndexFor(['member_level_id', 'global_topup_package_id'])")
        ->not->toContain("foreignId('global_topup_package_id')->constrained()");
});
