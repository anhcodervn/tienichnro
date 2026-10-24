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
        Schema::table('seo_posts', function (Blueprint $table): void {
            $table->string('language', 35)->default('vi');
            $table->unsignedInteger('lock_version')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_posts', function (Blueprint $table): void {
            $table->dropColumn(['language', 'lock_version']);
        });
    }
};
