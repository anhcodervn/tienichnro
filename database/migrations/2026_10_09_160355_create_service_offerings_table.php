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
        Schema::create('service_offerings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('icon_type', 16)->default('icon');
            $table->string('icon', 80)->default('bx-store');
            $table->string('image_url', 2048)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->text('maintenance_message');
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['is_enabled', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_offerings');
    }
};
