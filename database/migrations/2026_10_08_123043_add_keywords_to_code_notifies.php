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
        Schema::table('code_notifies', function (Blueprint $table): void {
            $table->json('keywords')->nullable();
        });
        DB::table('code_notifies')->where('code', 'BOSS')->update(['keywords' => json_encode(['vừa xuất hiện tại', 'vừa bị tiêu diệt bởi'], JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('code_notifies', function (Blueprint $table): void {
            $table->dropColumn('keywords');
        });
    }
};
