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
            $table->unsignedBigInteger('collaborator_settlement_amount')->nullable()->after('collaborator_total_cost');
            $table->foreignId('collaborator_wallet_transaction_id')->nullable()->after('collaborator_settlement_amount')->constrained('wallet_transactions')->nullOnDelete();
            $table->timestamp('collaborator_settled_at')->nullable()->after('collaborator_wallet_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_service_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collaborator_wallet_transaction_id');
            $table->dropColumn(['collaborator_settlement_amount', 'collaborator_settled_at']);
        });
    }
};
