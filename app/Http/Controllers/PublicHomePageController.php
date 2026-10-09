<?php

namespace App\Http\Controllers;

use App\Features\Admin\Seo\Services\HomeSeoService;
use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Models\SeoPost;
use App\Support\EditorContentRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicHomePageController extends Controller
{
    public function __invoke(Request $request, HomeSeoService $homeSeoService, EditorContentRenderer $renderer, ToolAvailabilityService $services): View|RedirectResponse
    {
        if ($request->hasAny(['q', 'page'])) {
            return redirect()->route('seo.index', $request->only(['q', 'page']));
        }
        $homeSeo = $homeSeoService->settings();
        $homeFaq = collect($homeSeo['is_published'] ? $homeSeo['faqs'] : [])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['question'] ?? null) && filled($item['answer'] ?? null))->values();
        $latestPosts = SeoPost::query()->with('category:id,name,slug,is_active')
            ->whereIn('type', ['knowledge', 'guide'])->where('status', 'published')
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('seo_category_id')->orWhereHas('category', fn ($categories) => $categories->where('is_active', true)))
            ->orderByDesc('published_at')->orderByDesc('id')->limit(3)->get();

        return view('client.home.index', [
            'homeSeo' => $homeSeo, 'homeFaq' => $homeFaq,
            'homeContentHtml' => $homeSeo['is_published'] ? $renderer->renderNodes($homeSeo['content'])->toHtml() : '',
            'homeFaqSchema' => $homeFaq->isNotEmpty() ? [
                '@context' => 'https://schema.org', '@type' => 'FAQPage',
                'mainEntity' => $homeFaq->map(fn (array $item): array => [
                    '@type' => 'Question', 'name' => $item['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
                ])->all(),
            ] : null,
            'tools' => $services->all(), 'latestPosts' => $latestPosts,
        ]);
    }
}
