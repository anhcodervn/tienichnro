<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Support\SitemapUrlService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __construct(
        protected SitemapUrlService $sitemapUrlService,
    ) {}

    public function __invoke(): Response
    {
        $urls = $this->sitemapUrlService->urls();

        return response()
            ->view('client.sitemap', compact('urls'))
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
