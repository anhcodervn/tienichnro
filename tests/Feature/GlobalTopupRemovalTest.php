<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('global topup endpoints and pricing tables are retired', function (): void {
    foreach (['global_topup_packages', 'global_topup_package_game_settings', 'user_global_package_prices', 'user_global_prices', 'member_level_global_package_prices'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
    foreach (Route::getRoutes() as $route) {
        expect($route->uri())->not->toContain('global-topup', 'global-prices');
    }
    expect(method_exists(new User, 'orders'))->toBeFalse();
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->getJson('/api/admin-api/global-topup-packages')->assertNotFound();
    $this->getJson('/api/admin-api/global-topup-rewards')->assertNotFound();
    $this->get('/thong-bao-game')->assertOk();
});

test('empty global pricing retirement is repeatable and preserves archived order snapshots', function (): void {
    Schema::create('orders', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('global_topup_package_id');
        $table->string('global_topup_package_name');
    });
    DB::table('orders')->insert(['id' => 1, 'global_topup_package_id' => 123, 'global_topup_package_name' => 'Archived package']);
    $migration = require database_path('migrations/2026_10_08_102136_remove_empty_legacy_global_topup_prices.php');
    $migration->down();
    $migration->down();
    expect(Schema::hasColumns('user_global_prices', ['user_id', 'discount_basis_points', 'minimum_profit', 'is_active', 'created_at', 'updated_at']))->toBeTrue();
    expect(collect(Schema::getForeignKeys('user_global_prices'))->pluck('foreign_table'))->toContain('users');

    $migration->up();
    $migration->up();
    expect(Schema::hasTable('user_global_prices'))->toBeFalse();
    $this->assertDatabaseHas('orders', ['id' => 1, 'global_topup_package_id' => 123, 'global_topup_package_name' => 'Archived package']);
});

test('global pricing retirement refuses to discard existing prices', function (): void {
    $user = User::factory()->create();
    $migration = require database_path('migrations/2026_10_08_102136_remove_empty_legacy_global_topup_prices.php');
    $migration->down();
    DB::table('user_global_prices')->insert(['user_id' => $user->id, 'discount_basis_points' => 500]);

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'Cannot remove user_global_prices');
    $this->assertDatabaseHas('user_global_prices', ['user_id' => $user->id, 'discount_basis_points' => 500]);
});
