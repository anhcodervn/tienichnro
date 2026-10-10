<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_products', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('product_code', 64)->unique();
            $table->text('description')->nullable();
            $table->string('minimum_version', 32)->default('1.0.0');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('heartbeat_interval')->default(20);
            $table->unsignedInteger('lease_duration')->default(60);
            $table->unsignedInteger('transfer_cooldown')->default(1800);
            $table->unsignedInteger('max_active_devices')->default(1);
            $table->unsignedInteger('offline_grace')->default(0);
            $table->timestamps();
        });
        Schema::create('license_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('license_products')->restrictOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedInteger('max_active_devices')->default(1);
            $table->unsignedInteger('transfer_cooldown')->default(1800);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('licenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('license_products')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('license_plans')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 10)->index();
            $table->string('status', 16)->default('unused')->index();
            $table->unsignedInteger('duration_days')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_transfer_at')->nullable();
            $table->unsignedBigInteger('generation')->default(0);
            $table->unsignedInteger('max_active_devices')->default(1);
            $table->unsignedInteger('transfer_cooldown')->default(1800);
            $table->uuid('current_device_uuid')->nullable();
            $table->timestamps();
        });
        Schema::create('license_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_uuid');
            $table->string('device_name', 120);
            $table->string('hwid_hash', 64)->nullable();
            $table->text('public_key');
            $table->string('assurance', 32)->default('unverified');
            $table->timestamp('first_activated_at');
            $table->timestamp('last_seen_at');
            $table->string('last_ip', 45)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->unique(['license_id', 'device_uuid']);
        });
        Schema::create('license_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('license_devices')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('generation');
            $table->string('status', 16)->default('active');
            $table->timestamp('started_at');
            $table->timestamp('last_heartbeat_at');
            $table->timestamp('lease_expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->timestamps();
            $table->index(['license_id', 'status']);
        });
        Schema::create('license_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('license_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 64);
            $table->string('reason')->nullable();
            $table->uuid('device_uuid')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
        Schema::create('license_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('nonce_hash', 64);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
        });
        Schema::create('license_nonces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('license_devices')->cascadeOnDelete();
            $table->string('nonce_hash', 64);
            $table->timestamp('expires_at')->index();
            $table->unique(['device_id', 'nonce_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_nonces');
        Schema::dropIfExists('license_challenges');
        Schema::dropIfExists('license_events');
        Schema::dropIfExists('license_sessions');
        Schema::dropIfExists('license_devices');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('license_plans');
        Schema::dropIfExists('license_products');
    }
};
