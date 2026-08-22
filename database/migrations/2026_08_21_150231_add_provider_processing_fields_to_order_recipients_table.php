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
        Schema::table('order_recipients', function (Blueprint $table): void {
            $table->string('provider_request_id', 64)->nullable()->after('status')->unique();
            $table->string('provider_status', 30)->nullable()->after('provider_reference');
            $table->json('provider_response')->nullable()->after('provider_status');
            $table->unsignedSmallInteger('status_check_attempts')->default(0)->after('provider_response');
            $table->timestamp('submitted_at')->nullable()->after('failure_reason');
            $table->timestamp('last_checked_at')->nullable()->after('submitted_at');
            $table->timestamp('completed_at')->nullable()->after('last_checked_at');
            $table->timestamp('failed_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_recipients', function (Blueprint $table): void {
            $table->dropUnique(['provider_request_id']);
            $table->dropColumn([
                'provider_request_id',
                'provider_status',
                'provider_response',
                'status_check_attempts',
                'submitted_at',
                'last_checked_at',
                'completed_at',
                'failed_at',
            ]);
        });
    }
};
