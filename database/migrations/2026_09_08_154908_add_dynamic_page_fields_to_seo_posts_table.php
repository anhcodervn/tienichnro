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
            $table->string('type')->default('knowledge')->index()->after('seo_category_id');
            $table->foreignId('service_id')->nullable()->after('type')->constrained('games')->nullOnDelete();
            $table->json('faq')->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn(['type', 'faq']);
        });
    }
};
