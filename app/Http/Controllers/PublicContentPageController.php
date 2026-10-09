<?php

namespace App\Http\Controllers;

use App\Support\EditorContentRenderer;
use App\Support\SettingStore;
use Illuminate\Contracts\View\View;

class PublicContentPageController extends Controller
{
    public function __construct(
        protected EditorContentRenderer $contentRenderer,
    ) {}

    /**
     * @return array<string, array<string, string>>
     */
    protected function pageMap(): array
    {
        return [
            'gioi-thieu' => [
                'fallback_title' => 'Giới thiệu',
                'fallback_description' => 'Website chia sẻ tiện ích, hướng dẫn và kinh nghiệm chơi Ngọc Rồng Online.',
                'title_key' => 'about_page_title',
                'excerpt_key' => 'about_page_excerpt',
                'content_key' => 'about_page_content',
                'seo_title_key' => 'about_page_seo_title',
                'seo_description_key' => 'about_page_seo_description',
                'published_key' => 'about_page_is_published',
            ],
            'lien-he' => [
                'fallback_title' => 'Liên hệ',
                'fallback_description' => 'Liên hệ ban biên tập để góp ý, gửi bài viết và trao đổi về Ngọc Rồng Online.',
                'title_key' => 'contact_page_title',
                'excerpt_key' => 'contact_page_excerpt',
                'content_key' => 'contact_page_content',
                'seo_title_key' => 'contact_page_seo_title',
                'seo_description_key' => 'contact_page_seo_description',
                'published_key' => 'contact_page_is_published',
            ],
            'dieu-khoan-su-dung' => [
                'fallback_title' => 'Điều khoản sử dụng',
                'fallback_description' => 'Các quy định khi sử dụng dịch vụ nạp game, trách nhiệm người dùng và giới hạn hệ thống.',
                'title_key' => 'terms_page_title',
                'excerpt_key' => 'terms_page_excerpt',
                'content_key' => 'terms_page_content',
                'seo_title_key' => 'terms_page_seo_title',
                'seo_description_key' => 'terms_page_seo_description',
                'published_key' => 'terms_page_is_published',
            ],
            'chinh-sach-bao-mat' => [
                'fallback_title' => 'Chính sách bảo mật',
                'fallback_description' => 'Cách hệ thống thu thập, lưu trữ, bảo vệ và xử lý dữ liệu của người dùng.',
                'title_key' => 'privacy_page_title',
                'excerpt_key' => 'privacy_page_excerpt',
                'content_key' => 'privacy_page_content',
                'seo_title_key' => 'privacy_page_seo_title',
                'seo_description_key' => 'privacy_page_seo_description',
                'published_key' => 'privacy_page_is_published',
            ],
            'huong-dan' => [
                'fallback_title' => 'Hướng dẫn Ngọc Rồng Online',
                'fallback_description' => 'Tổng hợp hướng dẫn nhiệm vụ, cách chơi và các tiện ích dành cho Ngọc Rồng Online.',
                'title_key' => 'guide_page_title',
                'excerpt_key' => 'guide_page_excerpt',
                'content_key' => 'guide_page_content',
                'seo_title_key' => 'guide_page_seo_title',
                'seo_description_key' => 'guide_page_seo_description',
                'published_key' => 'guide_page_is_published',
            ],
            'cau-hoi-thuong-gap' => [
                'fallback_title' => 'Câu hỏi thường gặp',
                'fallback_description' => 'Giải đáp các câu hỏi thường gặp về nội dung và tiện ích trên website.',
                'title_key' => 'faq_page_title',
                'excerpt_key' => 'faq_page_excerpt',
                'content_key' => 'faq_page_content',
                'seo_title_key' => 'faq_page_seo_title',
                'seo_description_key' => 'faq_page_seo_description',
                'published_key' => 'faq_page_is_published',
            ],
        ];
    }

    public function show(string $slug, SettingStore $settingStore): View
    {
        $page = $this->pageMap()[$slug] ?? abort(404);

        $systemSettings = $settingStore->getMany([
            'site_name' => config('app.name', 'Nạp Carot'),
            'site_domain' => '',
            'site_description' => '',
            'support_email' => '',
            'hotline' => '',
            'address' => '',
            'facebook' => '',
            'zalo' => '',
            'youtube' => '',
            'meta_title' => '',
            'meta_description' => '',
            'light_logo' => '',
            'dark_logo' => '',
            'favicon' => '',
            'og_image' => '',
            'gtm_id' => '',
            'meta_pixel_id' => '',
            $page['title_key'] => $page['fallback_title'],
            $page['excerpt_key'] => $page['fallback_description'],
            $page['content_key'] => [],
            $page['seo_title_key'] => '',
            $page['seo_description_key'] => '',
            $page['published_key'] => true,
        ]);

        abort_if(! (bool) ($systemSettings[$page['published_key']] ?? true), 404);

        $pageTitle = (string) ($systemSettings[$page['title_key']] ?: $page['fallback_title']);
        $pageDescription = (string) ($systemSettings[$page['excerpt_key']] ?: $page['fallback_description']);
        $pageMetaTitle = (string) ($systemSettings[$page['seo_title_key']] ?: $pageTitle.' | '.($systemSettings['site_name'] ?: config('app.name', 'Nạp Carot')));
        $pageMetaDescription = (string) ($systemSettings[$page['seo_description_key']] ?: $pageDescription);
        $content = is_array($systemSettings[$page['content_key']] ?? null) ? $systemSettings[$page['content_key']] : [];

        return view('pages.content.show', [
            'systemSettings' => $systemSettings,
            'pageSlug' => $slug,
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'pageMetaTitle' => $pageMetaTitle,
            'pageMetaDescription' => $pageMetaDescription,
            'contentBlocks' => $content,
            'contentHtml' => $this->contentRenderer->renderNodes($content),
            'contentLinks' => collect($this->pageMap())
                ->map(fn (array $item) => ['title' => $item['fallback_title']])
                ->all(),
        ]);
    }
}
