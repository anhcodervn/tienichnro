<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captcha_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 80)->unique();
            $table->string('driver', 40);
            $table->string('endpoint', 2048);
            $table->text('api_key')->nullable();
            $table->unsignedInteger('priority')->default(10);
            $table->unsignedSmallInteger('timeout')->default(15);
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
            $table->index(['is_enabled', 'priority', 'id']);
        });
        Schema::table('captcha_solves', function (Blueprint $table): void {
            $table->string('provider_name', 80)->nullable();
        });
        Schema::create('captcha_provider_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('captcha_solve_id')->constrained()->cascadeOnDelete();
            $table->foreignId('captcha_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_name', 80);
            $table->string('driver', 40);
            $table->string('status', 20)->default('processing');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captcha_provider_attempts');
        Schema::table('captcha_solves', function (Blueprint $table): void {
            $table->dropColumn('provider_name');
        });
        Schema::dropIfExists('captcha_providers');
    }
};
