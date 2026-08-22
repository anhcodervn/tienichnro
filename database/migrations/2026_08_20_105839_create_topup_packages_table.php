<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topup_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('denomination')->nullable();
            $table->unsignedBigInteger('carot_amount')->nullable();
            $table->decimal('price', 20, 2);
            $table->decimal('original_price', 20, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('bonus_text')->nullable();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'status', 'sort_order']);
            $table->index(['game_server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topup_packages');
    }
};
