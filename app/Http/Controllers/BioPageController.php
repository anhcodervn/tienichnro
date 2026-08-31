<?php

namespace App\Http\Controllers;

use App\Support\SafeNavigationUrl;
use App\Support\SettingStore;
use Illuminate\Contracts\View\View;

class BioPageController extends Controller
{
    /** @var array<string, string> */
    private const BIO_ICON_CLASSES = [
        'link' => 'bx bx-link-alt',
        'website' => 'bx bx-globe-alt',
        'facebook' => 'bxl bx-facebook-circle',
        'messenger' => 'bxl bx-messenger',
        'youtube' => 'bxl bx-youtube',
        'discord' => 'bxl bx-discord-alt',
        'telegram' => 'bxl bx-telegram',
        'tiktok' => 'bxl bx-tiktok',
        'instagram' => 'bxl bx-instagram',
        'zalo' => 'bx bx-message-circle-dots',
        'email' => 'bx bx-envelope',
        'phone' => 'bx bx-phone',
        'store' => 'bx bx-store',
        'community' => 'bx bx-group',
    ];

    public function __invoke(SettingStore $settingStore): View
    {
        $settings = $settingStore->getMany($this->defaults());

        return view('pages.bio', [
            'profile' => $this->profile($settings),
            'links' => $this->activeLinks($settings['bio_links']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'site_name' => config('app.name', 'Nạp Carot'),
            'light_logo' => '',
            'dark_logo' => '',
            'bio_title' => '',
            'bio_description' => '',
            'bio_avatar_url' => '',
            'bio_links' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{title: string, description: string, avatar_url: string}
     */
    private function profile(array $settings): array
    {
        $avatarUrl = trim((string) ($settings['bio_avatar_url'] ?: ($settings['dark_logo'] ?: $settings['light_logo'])));

        return [
            'title' => trim((string) ($settings['bio_title'] ?: $settings['site_name'])),
            'description' => trim((string) $settings['bio_description']),
            'avatar_url' => SafeNavigationUrl::passes($avatarUrl) ? $avatarUrl : '',
        ];
    }

    /**
     * @return array<int, array{label: string, url: string, icon_class: string}>
     */
    private function activeLinks(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        return collect($links)
            ->filter(fn (mixed $link): bool => is_array($link)
                && ($link['is_active'] ?? false) === true
                && is_string($link['label'] ?? null)
                && SafeNavigationUrl::passes($link['url'] ?? null))
            ->map(fn (array $link): array => [
                'label' => trim($link['label']),
                'url' => trim($link['url']),
                'icon_class' => self::BIO_ICON_CLASSES[$link['icon'] ?? 'link'] ?? self::BIO_ICON_CLASSES['link'],
            ])
            ->values()
            ->all();
    }
}
