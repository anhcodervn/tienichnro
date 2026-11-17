<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_image_accounts', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('charged_amount')->default(0);
            $table->foreignId('charge_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->foreignId('refund_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
        });
        DB::table('game_image_accounts')->whereNotNull('capture_directory')->update(['status' => 'success', 'completed_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_image_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('charge_transaction_id');
            $table->dropConstrainedForeignId('refund_transaction_id');
            $table->dropColumn(['status', 'error_message', 'charged_amount', 'processing_at', 'completed_at', 'refunded_at']);
        });
    }
};
