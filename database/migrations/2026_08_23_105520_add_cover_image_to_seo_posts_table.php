<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_posts', function (Blueprint $table): void {
            $table->text('cover_image')->nullable()->after('content');
            $table->text('canonical_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('seo_posts', function (Blueprint $table): void {
            $table->string('canonical_url')->nullable()->change();
            $table->dropColumn('cover_image');
        });
    }
};
