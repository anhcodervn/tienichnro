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
        $this->deferSqliteForeignKeys();
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedInteger('server_code')->nullable(false)->change();
            $table->unique('server_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->deferSqliteForeignKeys();
        Schema::table('servers', function (Blueprint $table) {
            $table->dropUnique(['server_code']);
            $table->unsignedInteger('server_code')->nullable()->change();
        });
    }

    private function deferSqliteForeignKeys(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA defer_foreign_keys = ON');
        }
    }
};
