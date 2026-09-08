<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Support\SitemapUrlService;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class SitemapController extends Controller
{
    public function __construct(
        protected SitemapUrlService $sitemapUrlService,
    ) {}

    public function index(): Response
    {
        $sitemaps = collect([
            ['loc' => route('sitemap.pages'), 'lastmod' => $this->latestLastmod($this->sitemapUrlService->pageUrls())],
            ['loc' => route('sitemap.articles'), 'lastmod' => $this->latestLastmod($this->sitemapUrlService->articleUrls())],
            ['loc' => route('sitemap.categories'), 'lastmod' => $this->latestLastmod($this->sitemapUrlService->categoryUrls())],
            ['loc' => route('sitemap.games'), 'lastmod' => $this->latestLastmod($this->sitemapUrlService->gameUrls())],
        ]);

        return response()
            ->view('client.sitemap-index', compact('sitemaps'))
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function pages(): Response
    {
        return $this->urlset($this->sitemapUrlService->pageUrls());
    }

    public function articles(): Response
    {
        return $this->urlset($this->sitemapUrlService->articleUrls());
    }

    public function categories(): Response
    {
        return $this->urlset($this->sitemapUrlService->categoryUrls());
    }

    public function games(): Response
    {
        return $this->urlset($this->sitemapUrlService->gameUrls());
    }

    /** @param Collection<int, array{loc: string, lastmod: mixed}> $urls */
    private function urlset(Collection $urls): Response
    {
        return response()
            ->view('client.sitemap', compact('urls'))
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /** @param Collection<int, array{loc: string, lastmod: mixed}> $urls */
    private function latestLastmod(Collection $urls): mixed
    {
        return $urls->pluck('lastmod')->filter()->sortDesc()->first();
    }
}
