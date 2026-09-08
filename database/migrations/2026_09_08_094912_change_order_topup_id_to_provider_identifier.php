<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['topup_id']);
            $table->string('topup_id', 100)->nullable()->change();
            $table->index('topup_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['topup_id']);
            $table->ulid('topup_id')->nullable()->change();
            $table->unique('topup_id');
        });
    }
};
