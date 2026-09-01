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
        Schema::create('member_level_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('earned_level_id')->nullable()->constrained('member_levels')->nullOnDelete();
            $table->foreignId('manual_level_id')->nullable()->constrained('member_levels')->nullOnDelete();
            $table->unsignedBigInteger('lifetime_completed_amount')->default(0);
            $table->timestamp('last_qualified_order_at')->nullable()->index();
            $table->timestamp('manual_level_expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_level_accounts');
    }
};
