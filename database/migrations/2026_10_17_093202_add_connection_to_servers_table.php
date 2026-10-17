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
        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('connection')->default(false);
            $table->timestamp('last_ping_at')->nullable();
            $table->index(['connection', 'last_ping_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['connection', 'last_ping_at']);
            $table->dropColumn(['connection', 'last_ping_at']);
        });
    }
};
