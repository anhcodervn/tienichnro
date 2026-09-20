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
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('expired_at')->nullable()->after('cancelled_at')->index();
        });

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->timestamp('expired_at')->nullable()->after('status')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropIndex(['expired_at']);
            $table->dropColumn('expired_at');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['expired_at']);
            $table->dropColumn('expired_at');
        });
    }
};
