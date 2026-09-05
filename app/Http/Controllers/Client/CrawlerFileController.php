<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Support\CrawlerFileContent;
use App\Support\SettingStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CrawlerFileController extends Controller
{
    public function __construct(
        protected CrawlerFileContent $crawlerFileContent,
    ) {}

    public function robots(Request $request, SettingStore $settingStore): Response
    {
        return $this->plainTextResponse($request, $this->crawlerFileContent->robots($settingStore));
    }

    public function ads(Request $request): Response
    {
        return $this->plainTextResponse($request, $this->crawlerFileContent->ads());
    }

    private function plainTextResponse(Request $request, string $content): Response
    {
        $response = response($content)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'public, max-age=300');

        $response->setEtag(hash('sha256', $content));
        $response->isNotModified($request);

        return $response;
    }
}
