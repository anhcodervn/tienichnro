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
        Schema::create('game_seo_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->string('h1')->nullable();
            $table->string('article_title')->nullable();
            $table->json('content')->nullable();
            $table->text('og_image')->nullable();
            $table->string('og_image_alt')->nullable();
            $table->text('canonical_url')->nullable();
            $table->string('robots', 30)->default('index,follow');
            $table->json('faqs')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->boolean('breadcrumb_schema')->default(true);
            $table->boolean('webpage_schema')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_seo_settings');
    }
};
