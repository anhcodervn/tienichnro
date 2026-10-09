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
        Schema::table('code_notifies', function (Blueprint $table) {
            $table->json('additional_filters')->nullable();
        });
        DB::table('code_notifies')->where('code', 'BOSS')->update(['additional_filters' => json_encode(['boss', 'state'])]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('code_notifies', function (Blueprint $table) {
            $table->dropColumn('additional_filters');
        });
    }
};
