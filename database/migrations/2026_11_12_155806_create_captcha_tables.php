<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captcha_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->unsignedBigInteger('reserved')->default(0);
            $table->timestamps();
        });
        Schema::create('captcha_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_key', 100);
            $table->string('direction', 10);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->unsignedBigInteger('cost')->default(0);
            $table->string('description', 500);
            $table->timestamps();
            $table->unique(['user_id', 'request_key']);
        });
        Schema::create('captcha_solves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id');
            $table->string('image_hash', 64);
            $table->string('image_path');
            $table->string('source', 10);
            $table->string('status', 20)->default('processing')->index();
            $table->text('result')->nullable();
            $table->json('response')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'request_id']);
            $table->index(['user_id', 'status', 'created_at']);
        });
        Schema::create('captcha_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('prefix', 16);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captcha_api_keys');
        Schema::dropIfExists('captcha_solves');
        Schema::dropIfExists('captcha_transactions');
        Schema::dropIfExists('captcha_wallets');
    }
};
