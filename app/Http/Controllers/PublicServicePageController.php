<?php

namespace App\Http\Controllers;

use App\Features\Admin\Setting\Services\ServiceCatalogService;
use App\Models\ServicePackage;
use App\Support\SafeNavigationUrl;
use Illuminate\Contracts\View\View;

class PublicServicePageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ServiceCatalogService $services): View
    {
        $items = $services->all()->map(function (array $service): array {
            $url = $service['url'] ?? null;

            return [...$service, 'public_url' => is_string($url) && SafeNavigationUrl::passes($url) ? $url : null];
        });
        $packages = ServicePackage::query()->where('is_active', true)
            ->whereIn('service_code', $items->where('is_enabled', true)->pluck('code'))
            ->orderBy('sort_order')->orderBy('id')->get()->groupBy('service_code');

        return view('pages.services.index', ['services' => $items, 'packages' => $packages]);
    }
}
