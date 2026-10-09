<?php

use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('standard migrations never create retired commerce tables on a fresh database', function (): void {
    $retiredTables = ['wallets', 'wallet_transactions', 'orders', 'order_recipients', 'payment_transactions', 'games', 'game_servers', 'topup_packages', 'topup_providers', 'global_topup_packages', 'tenant_package_prices', 'affiliate_profiles', 'game_services', 'member_levels', 'config_recharge', 'coupons', 'api_keys', 'api_logs'];
    $createdTables = [];
    DB::listen(function ($query) use (&$createdTables): void {
        if (preg_match('/^create table ["`]?([^"`\s(]+)/i', $query->sql, $matches)) {
            $createdTables[] = $matches[1];
        }
    });

    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    foreach ($retiredTables as $table) {
        expect($createdTables)->not->toContain($table);
        expect(Schema::hasTable($table))->toBeFalse();
    }
    expect(Schema::hasColumn('users', 'referral_code'))->toBeFalse();
    expect(Schema::hasColumn('users', 'game_service_secondary_password'))->toBeFalse();
    expect(Schema::getTables())->toHaveCount(27);

    $this->artisan('site:setup')->assertSuccessful();
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/user')->assertOk();
    $this->get('/thong-bao-game')->assertOk();
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

test('article setup initializes an empty database and preserves content when rerun', function (): void {
    $this->artisan('site:setup')->assertSuccessful();

    foreach (['users', 'sessions', 'user_sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'personal_access_tokens', 'settings', 'user_logs', 'queue_logs', 'contact_feedbacks', 'seo_categories', 'seo_posts', 'seo_redirects', 'admin_audit_logs', 'notifications', 'notification_reads'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    foreach (['wallets', 'orders', 'games', 'game_servers', 'game_seo_settings', 'topup_packages', 'topup_providers', 'global_topup_packages', 'user_global_prices', 'affiliate_profiles', 'packages', 'game_services', 'game_service_orders', 'collaborator_game_service_permissions'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }

    $this->assertDatabaseCount('seo_categories', 4);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('servers', 22);
    $this->assertDatabaseCount('code_notifies', 8);
    $this->get('/thong-bao-game')->assertOk();
    $user = User::query()->create(['full_name' => 'Article editor', 'email' => 'editor@example.test', 'password' => 'editor-password', 'role' => 'admin', 'status' => 'active']);
    expect($user->username)->not->toBeEmpty();
    expect(Hash::check('editor-password', $user->password))->toBeTrue();

    $post = SeoPost::query()->create(['title' => 'NRO guide', 'slug' => 'nro-guide', 'type' => 'guide', 'status' => 'published', 'published_at' => now()->subMinute(), 'content' => []]);
    $this->artisan('site:setup')->assertSuccessful();
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('seo_categories', 4);
    $this->assertDatabaseHas('seo_posts', ['id' => $post->id, 'slug' => 'nro-guide']);
    $this->get('/')->assertOk()->assertSee('NRO guide');
    $this->post('/dang-nhap', ['login' => $user->email, 'password' => 'editor-password'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
    $this->getJson('/api/user')->assertOk()->assertJsonMissingPath('wallet');
    $this->patch('/tai-khoan/thong-tin', ['full_name' => 'Updated editor', 'phone' => '0901234567'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'full_name' => 'Updated editor', 'phone' => '0901234567']);
    foreach (['posts', 'categories', 'home', 'overview', 'sitemaps'] as $page) {
        $this->getJson('/api/admin-api/seo/'.$page)->assertOk();
    }
    $this->getJson('/api/admin-api/users')->assertOk();
    $this->getJson('/api/admin-api/users/'.$user->id)->assertOk();
    $this->patchJson('/api/admin-api/seo/posts/'.$post->id, [
        'seo_category_id' => SeoCategory::query()->where('slug', 'huong-dan')->value('id'),
        'title' => 'Updated NRO guide', 'slug' => 'nro-guide', 'type' => 'guide', 'status' => 'published',
        'robots' => 'index,follow', 'content' => [], 'article_schema' => true, 'breadcrumb_schema' => true,
    ])->assertOk();
    $this->assertDatabaseHas('seo_posts', ['id' => $post->id, 'title' => 'Updated NRO guide']);
});

test('authentication setup fills missing supporting tables without changing existing accounts', function (): void {
    $this->artisan('site:setup')->assertSuccessful();
    $user = User::query()->create(['username' => 'existing-editor', 'email' => 'existing@example.test', 'password' => 'original-password', 'status' => 'active']);
    Schema::drop('sessions');
    Schema::drop('cache_locks');
    Schema::drop('job_batches');
    (require database_path('migrations/2026_10_06_103108_ensure_article_website_authentication_tables.php'))->up();
    expect(Schema::hasColumn('users', 'tenant_id'))->toBeFalse();
    foreach (['sessions', 'cache_locks', 'job_batches'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
    expect(Hash::check('original-password', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseCount('users', 1);
});

test('retirement detaches tenant columns and preserves accounts audit logs and global identity constraints', function (): void {
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    $user = User::factory()->create(['password' => 'preserved-password']);
    Schema::create('tenants', function (Blueprint $table): void {
        $table->id();
        $table->boolean('is_main');
    });
    DB::table('tenants')->insert(['id' => 1, 'is_main' => true]);
    foreach (['tenant_domains', 'tenant_settings', 'support_conversations', 'support_messages'] as $name) {
        Schema::create($name, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
        });
    }
    Schema::table('users', function (Blueprint $table): void {
        $table->foreignId('tenant_id')->nullable()->constrained('tenants');
        $table->index(['tenant_id', 'role']);
    });
    Schema::table('users', function (Blueprint $table): void {
        foreach (['username', 'email', 'phone'] as $column) {
            $table->dropUnique('users_'.$column.'_unique');
            $table->unique(['tenant_id', $column], 'users_tenant_'.$column.'_unique');
        }
    });
    Schema::table('admin_audit_logs', function (Blueprint $table): void {
        $table->foreignId('tenant_id')->nullable()->constrained('tenants');
        $table->index(['tenant_id', 'admin_id', 'created_at']);
    });
    DB::table('users')->where('id', $user->id)->update(['tenant_id' => 1]);
    DB::table('admin_audit_logs')->insert(['admin_id' => $user->id, 'action' => 'preserved', 'subject_type' => User::class, 'subject_id' => $user->id, 'tenant_id' => 1, 'created_at' => now()]);
    $migration = require database_path('migrations/2026_10_08_104336_remove_tenancy_and_support.php');
    $migration->up();
    $migration->up();
    expect(Hash::check('preserved-password', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseHas('admin_audit_logs', ['action' => 'preserved', 'admin_id' => $user->id]);
    foreach (['username', 'email', 'phone'] as $column) {
        expect(collect(Schema::getIndexes('users'))->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === [$column]))->toBeTrue();
    }
});
