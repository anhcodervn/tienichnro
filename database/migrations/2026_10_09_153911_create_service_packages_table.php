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
        Schema::create('service_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('service_code', 64);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->string('billing_type', 16);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['service_code', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_packages');
    }
};
