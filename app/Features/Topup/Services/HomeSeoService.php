<?php

namespace App\Features\Topup\Services;

use App\Support\SettingStore;

class HomeSeoService
{
    public function __construct(
        private readonly SettingStore $settingStore,
    ) {}

    /**
     * @return array{meta_title: string, meta_description: string, meta_keywords: string, h1: string, article_title: string, content: array<int, mixed>, faqs: array<int, mixed>, is_published: bool}
     */
    public function settings(): array
    {
        $settings = $this->settingStore->getMany([
            'home_seo_meta_title' => config('seo.homepage.title', ''),
            'home_seo_meta_description' => config('seo.homepage.description', ''),
            'home_seo_meta_keywords' => config('seo.homepage.keywords', ''),
            'home_seo_h1' => 'Nạp Carot Game Teamobi Nhanh Chóng, Giá Tốt',
            'home_seo_article_title' => 'Nạp Carot game Teamobi: chọn đúng game, rõ giá trước khi thanh toán',
            'home_seo_content' => [],
            'home_seo_faqs' => config('seo.faqs', []),
            'home_seo_is_published' => false,
        ]);

        return [
            'meta_title' => (string) $settings['home_seo_meta_title'],
            'meta_description' => (string) $settings['home_seo_meta_description'],
            'meta_keywords' => (string) $settings['home_seo_meta_keywords'],
            'h1' => (string) $settings['home_seo_h1'],
            'article_title' => (string) $settings['home_seo_article_title'],
            'content' => is_array($settings['home_seo_content']) ? $settings['home_seo_content'] : [],
            'faqs' => is_array($settings['home_seo_faqs']) ? $settings['home_seo_faqs'] : [],
            'is_published' => (bool) $settings['home_seo_is_published'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(array $payload): array
    {
        $this->settingStore->putMany([
            'home_seo_meta_title' => $payload['meta_title'] ?? '',
            'home_seo_meta_description' => $payload['meta_description'] ?? '',
            'home_seo_meta_keywords' => $payload['meta_keywords'] ?? '',
            'home_seo_h1' => $payload['h1'] ?? '',
            'home_seo_article_title' => $payload['article_title'] ?? '',
            'home_seo_content' => $payload['content'] ?? [],
            'home_seo_faqs' => $payload['faqs'] ?? [],
            'home_seo_is_published' => (bool) ($payload['is_published'] ?? false),
        ]);

        return $this->settings();
    }
}
