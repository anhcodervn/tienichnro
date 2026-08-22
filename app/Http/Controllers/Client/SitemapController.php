<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\SeoPost;
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
            SeoPost::query()->where('status', 'published')->where('published_at', '<=', now())->get(['slug', 'updated_at'])->map(fn (SeoPost $post): array => ['loc' => route('seo.show', $post->slug), 'lastmod' => $post->updated_at]),
        );

        return response()->view('client.sitemap', compact('urls'))->header('Content-Type', 'application/xml');
    }
}
