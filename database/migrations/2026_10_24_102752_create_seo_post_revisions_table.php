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
        Schema::create('seo_post_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seo_post_id')->constrained('seo_posts')->cascadeOnDelete();
            $table->json('snapshot');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['seo_post_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_post_revisions');
    }
};
