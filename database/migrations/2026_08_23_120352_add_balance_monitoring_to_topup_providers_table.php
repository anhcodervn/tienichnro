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
        Schema::table('topup_providers', function (Blueprint $table) {
            $table->unsignedBigInteger('balance')->nullable()->after('connection_config');
            $table->string('balance_currency', 10)->nullable()->after('balance');
            $table->string('balance_status', 20)->default('unchecked')->after('balance_currency');
            $table->timestamp('balance_checked_at')->nullable()->after('balance_status');
            $table->string('balance_error_code', 50)->nullable()->after('balance_checked_at');
            $table->string('balance_error_message', 500)->nullable()->after('balance_error_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topup_providers', function (Blueprint $table) {
            $table->dropColumn(['balance', 'balance_currency', 'balance_status', 'balance_checked_at', 'balance_error_code', 'balance_error_message']);
        });
    }
};
