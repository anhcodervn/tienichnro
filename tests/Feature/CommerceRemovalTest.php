<?php

use App\Models\SeoPost;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('commerce is retired while account administration and NRO still work', function (): void {
    foreach (['api_logs', 'api_keys', 'coupon_logs', 'coupons', 'member_level_order_credits', 'member_level_histories', 'member_level_accounts', 'order_recipients', 'payment_transactions', 'orders', 'wallet_transactions', 'wallets', 'recharge_bonus_tiers', 'config_recharge', 'member_levels'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
    foreach (Route::getRoutes() as $route) {
        expect($route->uri())->not->toContain('wallet', 'recharge', 'package', 'subscription', 'coupon', 'member-level');
    }
    $user = User::factory()->create(['role' => 'admin']);
    foreach (['wallet', 'wallets', 'orders', 'paymentTransactions', 'apiKeys', 'memberLevelAccount'] as $relation) {
        expect(method_exists($user, $relation))->toBeFalse();
    }
    $this->actingAs($user)->getJson('/api/user')->assertOk()->assertJsonMissingPath('wallet');
    $this->getJson('/api/admin-api/users')->assertOk()->assertJsonMissingPath('data.stats.total_user_wallet_balance');
    $this->getJson('/api/admin-api/users/'.$user->id)->assertOk()->assertJsonMissingPath('data.wallet');
    $this->getJson('/api/admin-api/settings/tax')->assertNotFound();
    $this->getJson('/api/admin-api/settings/service-articles')->assertNotFound();
    $this->get('/')->assertOk()->assertDontSee('href="https://napcarot.com"', false);
    $this->get('/thong-bao-game')->assertOk();
    $this->getJson('/api/admin-api/nro/bosses')->assertOk();
});

test('commerce cleanup removes empty transaction tables and default tiers while preserving content', function (): void {
    $user = User::factory()->create();
    $post = SeoPost::query()->create(['title' => 'NRO guide', 'slug' => 'preserved-nro-guide', 'content' => []]);
    foreach (['wallets', 'wallet_transactions', 'orders', 'member_levels'] as $table) {
        Schema::create($table, function (Blueprint $blueprint) use ($table): void {
            $blueprint->id();
            if ($table === 'member_levels') {
                $blueprint->string('code');
            }
        });
    }
    Schema::table('wallet_transactions', function (Blueprint $table): void {
        $table->foreignId('wallet_id')->constrained('wallets');
    });
    foreach (['member', 'level-1', 'level-2', 'level-3'] as $code) {
        DB::table('member_levels')->insert(['code' => $code]);
    }
    $migration = require database_path('migrations/2026_10_08_102528_retire_legacy_commerce_tables.php');
    $migration->up();
    $migration->up();

    foreach (['wallets', 'wallet_transactions', 'orders', 'member_levels'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertDatabaseHas('seo_posts', ['id' => $post->id, 'title' => 'NRO guide']);
});

test('commerce cleanup refuses existing financial data before dropping any tables', function (): void {
    foreach (['api_logs', 'wallets'] as $table) {
        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
        });
    }
    DB::table('wallets')->insert(['id' => 1]);
    $migration = require database_path('migrations/2026_10_08_102528_retire_legacy_commerce_tables.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'Cannot remove wallets');
    expect(Schema::hasTable('api_logs'))->toBeTrue();
    $this->assertDatabaseHas('wallets', ['id' => 1]);
});

test('commerce cleanup refuses custom tiers and references from other modules', function (): void {
    Schema::create('member_levels', function (Blueprint $table): void {
        $table->id();
        $table->string('code');
    });
    DB::table('member_levels')->insert(['code' => 'custom-tier']);
    $migration = require database_path('migrations/2026_10_08_102528_retire_legacy_commerce_tables.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'Cannot remove custom member levels');
    DB::table('member_levels')->delete();
    Schema::create('external_tier_links', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('member_level_id')->constrained('member_levels');
    });
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'external_tier_links still references member_levels');
    expect(Schema::hasTable('member_levels'))->toBeTrue();
});

test('registration login and logout work without commercial accounts or wallets', function (): void {
    Queue::fake();
    Notification::fake();
    Setting::query()->updateOrCreate(['key' => 'allow_register'], ['value' => true, 'type' => 'boolean']);
    $this->post('/dang-ky', [
        'username' => 'nro-reader', 'email' => 'nro-reader@example.test', 'password' => 'reader-password',
        'password_confirmation' => 'reader-password', 'accept_terms' => true,
    ])->assertSessionHasNoErrors()->assertRedirect(route('auth.login'));
    $user = User::query()->where('username', 'nro-reader')->firstOrFail();
    $this->post('/dang-nhap', ['login' => $user->email, 'password' => 'reader-password'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
    $this->get('/tai-khoan')->assertOk();
    $this->post('/dang-xuat')->assertRedirect();
    $this->assertGuest();
    expect(Schema::hasTable('wallets'))->toBeFalse()
        ->and(Schema::hasTable('member_level_accounts'))->toBeFalse();
});
