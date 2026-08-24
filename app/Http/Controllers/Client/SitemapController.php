<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\SeoCategory;
use App\Models\SeoPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'lastmod' => now()],
            ['loc' => route('pricing'), 'lastmod' => now()],
            ['loc' => route('seo.index'), 'lastmod' => now()],
            ['loc' => route('content.guide'), 'lastmod' => now()],
        ])->concat(
            Game::query()->active()->get(['slug', 'updated_at'])->map(fn (Game $game): array => ['loc' => route('topup.game', $game), 'lastmod' => $game->updated_at]),
        )->concat(
            SeoCategory::query()
                ->where('is_active', true)
                ->get(['slug', 'updated_at'])
                ->map(fn (SeoCategory $category): array => [
                    'loc' => route('seo.category', $category->slug),
                    'lastmod' => $category->updated_at,
                ]),
        )->concat(
            SeoPost::query()
                ->with('category:id,slug,is_active')
                ->where('status', 'published')
                ->where('published_at', '<=', now())
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('seo_category_id')
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('is_active', true));
                })
                ->get(['id', 'seo_category_id', 'slug', 'updated_at'])
                ->map(fn (SeoPost $post): array => [
                    'loc' => $post->category?->is_active
                        ? route('seo.show', ['categorySlug' => $post->category->slug, 'postSlug' => $post->slug])
                        : route('seo.legacy.show', $post->slug),
                    'lastmod' => $post->updated_at,
                ]),
        );

        return response()->view('client.sitemap', compact('urls'))->header('Content-Type', 'application/xml');
    }
}
