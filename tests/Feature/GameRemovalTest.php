<?php

use App\Models\SeoPost;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('legacy game catalog and topup are retired while NRO remains available', function (): void {
    foreach (['games', 'game_servers', 'game_seo_settings', 'global_topup_packages', 'global_topup_package_game_settings', 'topup_packages', 'topup_providers', 'topup_provider_prices', 'member_level_package_prices', 'member_level_global_package_prices', 'tenant_package_prices', 'user_package_prices', 'user_global_package_prices'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
    foreach (Route::getRoutes() as $route) {
        expect($route->uri())->not->toContain('/topup', 'seo/games', 'v1/catalog', 'v1/orders');
    }
    $this->get('/thong-bao-game')->assertOk();
    $this->getJson('/api/nro/notifies')->assertOk();

    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user)->getJson('/api/admin-api/nro/bosses')->assertOk();
    $this->getJson('/api/admin-api/seo/games')->assertNotFound();
    $this->getJson('/api/admin-api/topup/games')->assertNotFound();
    $this->getJson('/api/admin-api/users/'.$user->id)->assertOk();
});

test('game retirement preserves archived orders and article content when rerun', function (): void {
    Schema::create('orders', function (Blueprint $table): void {
        $table->id();
        $table->string('code');
    });
    foreach (['wallets', 'payment_transactions'] as $name) {
        Schema::create($name, function (Blueprint $table): void {
            $table->id();
        });
    }
    DB::table('orders')->insert(['id' => 1, 'code' => 'ARCHIVED-ORDER']);
    $post = SeoPost::query()->create(['title' => 'NRO guide', 'slug' => 'nro-guide', 'content' => []]);
    Schema::create('games', function (Blueprint $table): void {
        $table->id();
    });
    Schema::table('seo_posts', function (Blueprint $table): void {
        $table->foreign('service_id')->references('id')->on('games');
    });

    $migration = require database_path('migrations/2026_10_08_101442_remove_empty_legacy_game_catalog_tables.php');
    $migration->up();
    $migration->up();

    expect(Schema::hasTable('games'))->toBeFalse()
        ->and(Schema::hasTable('payment_transactions'))->toBeTrue()
        ->and(Schema::hasTable('wallets'))->toBeTrue();
    $post->update(['title' => 'Updated NRO guide']);
    $this->assertDatabaseHas('seo_posts', ['id' => $post->id, 'title' => 'Updated NRO guide']);
    $this->assertDatabaseHas('orders', ['id' => 1, 'code' => 'ARCHIVED-ORDER']);
    expect(collect(Schema::getForeignKeys('seo_posts'))->pluck('foreign_table'))->not->toContain('games');
});

test('game retirement refuses populated catalog tables before changing any schema', function (string $populatedTable): void {
    foreach (['game_seo_settings', $populatedTable] as $table) {
        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
        });
    }
    DB::table($populatedTable)->insert(['id' => 1]);
    $migration = require database_path('migrations/2026_10_08_101442_remove_empty_legacy_game_catalog_tables.php');

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, "Cannot remove {$populatedTable}");
    expect(Schema::hasTable('game_seo_settings'))->toBeTrue();
    $this->assertDatabaseHas($populatedTable, ['id' => 1]);
})->with(['games', 'topup_providers']);

test('game retirement refuses foreign keys from an unrelated feature', function (): void {
    Schema::create('games', function (Blueprint $table): void {
        $table->id();
    });
    Schema::create('external_game_links', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('game_id')->constrained('games');
    });
    $migration = require database_path('migrations/2026_10_08_101442_remove_empty_legacy_game_catalog_tables.php');

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'external_game_links still references games');
    expect(Schema::hasTable('games'))->toBeTrue()
        ->and(Schema::hasTable('external_game_links'))->toBeTrue();
});

test('bank callbacks are retired with the remaining commerce mechanisms', function (): void {
    $this->postJson('/api/recharge/webhook', ['amount' => 10000])->assertNotFound();
    expect(Schema::hasTable('wallets'))->toBeFalse();
});
