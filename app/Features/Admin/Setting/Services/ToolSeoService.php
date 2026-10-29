<?php

namespace App\Features\Admin\Setting\Services;

use App\Models\Setting;
use App\Support\EditorContentRenderer;
use App\Support\SettingStore;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

class ToolSeoService
{
    public function __construct(
        private readonly SettingStore $settings,
        private readonly ToolAvailabilityService $tools,
        private readonly EditorContentRenderer $renderer,
    ) {}

    /** @return array<string, mixed> */
    public function settings(string $code): array
    {
        $tool = $this->tools->find($code);
        abort_if($tool === null, 404);

        $seo = [...$this->defaults(), ...$this->settings->getArray('tool_seo.'.$code, []), ...$this->location($tool)];
        $seo['content'] = is_array($seo['content']) ? $seo['content'] : [];
        $seo['faq'] = is_array($seo['faq']) ? $seo['faq'] : [];
        $seo['title'] = (string) $seo['title'];

        return $seo;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(string $code, array $payload): array
    {
        return DB::transaction(function () use ($code, $payload): array {
            $key = 'tool_seo.'.$code;
            $current = $this->settings($code);
            if ($payload['is_published'] && (! $current['is_local'] || $current['slug'] === '')) {
                throw ValidationException::withMessages(['is_published' => 'Hãy cài liên kết nội bộ của công cụ trước khi xuất bản bài SEO.']);
            }
            Setting::query()->firstOrCreate(['key' => $key], ['type' => 'json', 'value' => '{}']);
            $record = Setting::query()->where('key', $key)->lockForUpdate()->firstOrFail();
            $stored = $this->settings->decode($record, []);
            $version = (int) ($stored['lock_version'] ?? 0);
            abort_if($version !== (int) $payload['expected_version'], 409, 'Bài SEO đã được thay đổi. Hãy tải lại trước khi lưu.');
            $this->settings->putArray($key, [
                ...Arr::only($payload, array_keys($this->defaults())),
                'lock_version' => $version + 1,
                'updated_at' => now()->toAtomString(),
            ]);

            return $this->settings($code);
        });
    }

    /** @return array<string, mixed>|null */
    public function publicPage(Request $request): ?array
    {
        $path = rtrim($request->getPathInfo(), '/') ?: '/';
        foreach ($this->tools->all() as $tool) {
            $location = $this->location($tool);
            if (! $location['is_local'] || $location['path'] !== $path) {
                continue;
            }
            $seo = $this->settings($tool['code']);
            if (! $seo['is_published']) {
                continue;
            }
            $seo['content_html'] = $this->renderer->renderNodes($seo['content']);
            $seo['faq'] = collect($seo['faq'] ?? [])->filter(fn (mixed $item): bool => is_array($item)
                && filled($item['question'] ?? null) && filled($item['answer'] ?? null))->values()->all();
            $seo['schemas'] = $this->schemas($seo, $tool);

            return $seo;
        }

        return null;
    }

    /** @param array<string, mixed> $tool
     * @return array{path: ?string, slug: string, canonical_url: ?string, is_local: bool}
     */
    private function location(array $tool): array
    {
        $link = $tool['url'] ?? null;
        if (! filled($link) && filled($tool['route'] ?? null) && Route::has($tool['route'])) {
            $link = route($tool['route'], $tool['parameters'] ?? []);
        }
        $path = is_string($link) ? parse_url($link, PHP_URL_PATH) : null;
        $host = is_string($link) ? parse_url($link, PHP_URL_HOST) : null;
        $local = is_string($path) && str_starts_with($path, '/')
            && (! $host || strtolower($host) === strtolower((string) parse_url(url('/'), PHP_URL_HOST)));
        $path = is_string($path) ? (rtrim($path, '/') ?: '/') : null;

        return ['path' => $path, 'slug' => ltrim($path ?? '', '/'), 'canonical_url' => $local ? url($path) : null, 'is_local' => $local];
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'title' => '', 'excerpt' => null, 'language' => 'vi', 'content' => [], 'faq' => [],
            'seo_title' => null, 'seo_description' => null, 'meta_keywords' => null, 'focus_keyword' => null,
            'cover_image' => null, 'cover_alt' => null, 'robots' => 'index,follow',
            'article_schema' => true, 'breadcrumb_schema' => true, 'is_published' => false, 'lock_version' => 0, 'updated_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $seo
     * @param  array<string, mixed>  $tool
     * @return list<array<string, mixed>>
     */
    private function schemas(array $seo, array $tool): array
    {
        $schemas = [];
        if ($seo['article_schema']) {
            $schemas[] = array_filter([
                '@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $seo['title'],
                'inLanguage' => $seo['language'], 'description' => $seo['seo_description'] ?: $seo['excerpt'],
                'mainEntityOfPage' => $seo['canonical_url'], 'dateModified' => $seo['updated_at'],
                'image' => $seo['cover_image'] ? url($seo['cover_image']) : null,
            ]);
        }
        if ($seo['breadcrumb_schema']) {
            $schemas[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => route('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $tool['name'], 'item' => $seo['canonical_url']],
            ]];
        }
        if ($seo['faq'] !== []) {
            $schemas[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(
                fn (array $item): array => ['@type' => 'Question', 'name' => $item['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']]],
                $seo['faq'],
            )];
        }

        return $schemas;
    }
}
