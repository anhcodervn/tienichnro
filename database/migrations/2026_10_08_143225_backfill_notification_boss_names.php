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
        DB::table('notifies')->whereNull('boss_name')->whereNotNull('boss_id')
            ->update(['boss_name' => DB::table('bosses')->select('name')->whereColumn('bosses.id', 'notifies.boss_id')]);
        DB::table('notifies')->whereNull('char_name')->whereNotNull('killed_by')->update(['char_name' => DB::raw('killed_by')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
