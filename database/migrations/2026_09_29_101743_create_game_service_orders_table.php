<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_service_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_service_package_price_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('game_name');
            $table->string('service_name');
            $table->string('package_name');
            $table->string('price_label');
            $table->string('server_name')->nullable();
            $table->json('payload');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('total_amount');
            $table->string('status', 20)->default('pending')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['game_id', 'game_service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_service_orders');
    }
};
