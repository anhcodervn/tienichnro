<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topup_providers', function (Blueprint $table): void {
            $table->string('price_sync_status', 20)->default('unchecked')->after('balance_error_message');
            $table->timestamp('price_synced_at')->nullable()->after('price_sync_status');
            $table->string('price_sync_error_code', 100)->nullable()->after('price_synced_at');
            $table->string('price_sync_error_message', 500)->nullable()->after('price_sync_error_code');
            $table->unsignedInteger('price_sync_latency_ms')->nullable()->after('price_sync_error_message');
        });

        Schema::table('topup_provider_prices', function (Blueprint $table): void {
            $table->boolean('available')->default(true)->after('price');
            $table->timestamp('synced_at')->nullable()->after('available');
        });
    }

    public function down(): void
    {
        Schema::table('topup_provider_prices', function (Blueprint $table): void {
            $table->dropColumn(['available', 'synced_at']);
        });

        Schema::table('topup_providers', function (Blueprint $table): void {
            $table->dropColumn([
                'price_sync_status',
                'price_synced_at',
                'price_sync_error_code',
                'price_sync_error_message',
                'price_sync_latency_ms',
            ]);
        });
    }
};
