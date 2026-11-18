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
        Schema::table('servers', function (Blueprint $table): void {
            $table->string('type', 20)->default('napcarot');
            $table->dropUnique(['server_code']);
            $table->dropUnique(['code']);
            $table->unique(['type', 'server_code']);
            $table->unique(['type', 'code']);
            $table->index(['type', 'sort_order', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropUnique(['type', 'server_code']);
            $table->dropUnique(['type', 'code']);
            $table->dropIndex(['type', 'sort_order', 'id']);
            $table->dropColumn('type');
            $table->unique('server_code');
            $table->unique('code');
        });
    }
};
