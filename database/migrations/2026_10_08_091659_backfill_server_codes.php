<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('servers')->whereNull('server_code')->update(['server_code' => DB::raw('id')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
