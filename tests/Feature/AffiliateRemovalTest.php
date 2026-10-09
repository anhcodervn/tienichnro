<?php

use App\Features\Topup\Observers\OrderObserver;
use App\Features\Topup\Services\OrderService;
use App\Features\Topup\Services\OrderStatusService;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('affiliate and collaborator routes and dependencies are retired', function (): void {
    foreach (Route::getRoutes() as $route) {
        expect($route->uri())->not->toContain('affiliate', 'collaborator', 'game-services', 'cong-tac-vien');
    }

    $user = User::factory()->create();
    expect($user->isFillable('referral_code'))->toBeFalse()
        ->and($user->isFillable('referred_by'))->toBeFalse()
        ->and(method_exists($user, 'affiliateProfile'))->toBeFalse()
        ->and(method_exists($user, 'allowedGameServices'))->toBeFalse();
    expect(class_exists(OrderObserver::class))->toBeFalse()
        ->and(class_exists(OrderService::class))->toBeFalse();
    $this->actingAs($user)->getJson('/api/user')->assertOk();
});

test('retirement drops empty tables and preserves user accounts', function (): void {
    $user = User::factory()->create();
    $tables = ['affiliate_profiles', 'affiliate_announcements', 'affiliate_announcement_reads', 'collaborator_game_service_permissions', 'game_services', 'game_server_game_service', 'game_service_orders', 'game_service_order_messages', 'game_service_order_progress', 'game_service_packages', 'game_service_package_prices'];
    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
        });
    }

    $migration = require database_path('migrations/2026_10_08_100604_remove_empty_affiliate_and_collaborator_tables.php');
    $migration->up();
    $migration->up();

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

test('retirement checks all tables for data before dropping any table', function (): void {
    foreach (['affiliate_announcement_reads', 'affiliate_profiles'] as $table) {
        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
        });
    }
    DB::table('affiliate_profiles')->insert(['id' => 1]);
    $migration = require database_path('migrations/2026_10_08_100604_remove_empty_affiliate_and_collaborator_tables.php');

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'Cannot remove affiliate_profiles');
    expect(Schema::hasTable('affiliate_announcement_reads'))->toBeTrue();
    $this->assertDatabaseHas('affiliate_profiles', ['id' => 1]);

    Schema::drop('affiliate_profiles');
    Schema::drop('affiliate_announcement_reads');
});

test('topup status mutations are retired along with the game module', function (): void {
    expect(class_exists(OrderStatusService::class))->toBeFalse();
    $this->postJson('/api/orders', [])->assertMethodNotAllowed();
    $this->patchJson('/api/admin-api/topup/orders/1/status', [])->assertNotFound();
});
