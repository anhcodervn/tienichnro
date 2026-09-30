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
        Schema::table('game_service_orders', function (Blueprint $table) {
            $table->foreignId('collaborator_hold_transaction_id')->nullable()->after('collaborator_settled_at')
                ->constrained('wallet_transactions')->nullOnDelete();
            $table->foreignId('collaborator_refund_transaction_id')->nullable()->after('collaborator_hold_transaction_id')
                ->constrained('wallet_transactions')->nullOnDelete();
            $table->timestamp('collaborator_held_at')->nullable()->after('collaborator_refund_transaction_id');
            $table->timestamp('collaborator_available_at')->nullable()->after('collaborator_held_at')->index();
            $table->timestamp('collaborator_refunded_at')->nullable()->after('collaborator_available_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_service_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collaborator_hold_transaction_id');
            $table->dropConstrainedForeignId('collaborator_refund_transaction_id');
            $table->dropColumn(['collaborator_held_at', 'collaborator_available_at', 'collaborator_refunded_at']);
        });
    }
};
