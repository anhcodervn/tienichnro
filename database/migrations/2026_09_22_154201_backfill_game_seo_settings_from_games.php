<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('game_seo_settings')) {
            return;
        }

        DB::table('games')
            ->select(['id', 'name', 'seo_title', 'seo_description', 'content', 'created_at', 'updated_at'])
            ->where(function ($query): void {
                $query->whereNotNull('seo_title')
                    ->orWhereNotNull('seo_description')
                    ->orWhereNotNull('content');
            })
            ->orderBy('id')
            ->each(function (object $game): void {
                $content = trim((string) $game->content);

                DB::table('game_seo_settings')->updateOrInsert(
                    ['game_id' => $game->id],
                    [
                        'meta_title' => $game->seo_title,
                        'meta_description' => $game->seo_description,
                        'h1' => 'Nạp game '.$game->name,
                        'article_title' => 'Hướng dẫn nạp '.$game->name,
                        'content' => $content !== '' ? json_encode([
                            ['type' => 'paragraph', 'children' => [['text' => $content]]],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                        'robots' => 'index,follow',
                        'is_published' => true,
                        'breadcrumb_schema' => true,
                        'webpage_schema' => true,
                        'created_at' => $game->created_at,
                        'updated_at' => $game->updated_at,
                    ],
                );
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Legacy SEO columns remain unchanged, so rollback does not need to copy data back.
    }
};
