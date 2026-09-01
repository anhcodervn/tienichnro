<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_topup_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 80)->unique();
            $table->unsignedBigInteger('denomination')->unique();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('original_price');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_topup_packages');
    }
};
