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
        Schema::create('member_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('rank')->unique();
            $table->unsignedBigInteger('lifetime_threshold')->default(0)->index();
            $table->unsignedBigInteger('maintenance_amount')->default(0);
            $table->unsignedSmallInteger('maintenance_days')->default(31);
            $table->unsignedSmallInteger('default_discount_bps')->default(0);
            $table->unsignedBigInteger('minimum_profit')->default(0);
            $table->string('color', 20)->default('#64748b');
            $table->string('icon', 50)->default('crown');
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_levels');
    }
};
