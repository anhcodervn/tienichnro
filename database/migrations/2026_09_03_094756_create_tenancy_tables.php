<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->foreignId('billing_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->boolean('is_main')->default(false)->index();
            $table->boolean('allow_below_cost')->default(false);
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'is_primary']);
        });

        Schema::create('tenant_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->longText('value')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('tenant_package_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topup_package_id')->constrained()->cascadeOnDelete();
            $table->string('pricing_mode', 24)->default('markup_amount');
            $table->unsignedBigInteger('fixed_price')->nullable();
            $table->unsignedBigInteger('markup_amount')->nullable();
            $table->unsignedInteger('markup_basis_points')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'topup_package_id']);
        });

        $appHost = Str::lower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $mainTenantId = DB::table('tenants')->insertGetId([
            'name' => (string) config('app.name', 'NapCarot'),
            'slug' => 'napcarot',
            'status' => 'active',
            'is_main' => true,
            'allow_below_cost' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenant_domains')->insert([
            'tenant_id' => $mainTenantId,
            'domain' => $appHost !== '' ? $appHost : 'localhost',
            'is_primary' => true,
            'is_verified' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_package_prices');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
