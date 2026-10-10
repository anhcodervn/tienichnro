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
        Schema::table('zalo_receive_notifications', function (Blueprint $table) {
            $table->unsignedInteger('char_server')->nullable();
            $table->index(['char_server', 'char_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zalo_receive_notifications', function (Blueprint $table) {
            $table->dropIndex(['char_server', 'char_name']);
            $table->dropColumn('char_server');
        });
    }
};
