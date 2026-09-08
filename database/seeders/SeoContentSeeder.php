<?php

namespace Database\Seeders;

use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Models\SeoRedirect;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeoContentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->normalizeKnownSiteName();
            $categories = $this->seedCategories();
            $this->migrateLegacyNgocRongCategory($categories['ngoc-rong-online']);
            $this->migrateKnownNgocRongPosts($categories['ngoc-rong-online']);
            $this->seedDraftPosts($categories);
        });
    }

    private function normalizeKnownSiteName(): void
    {
        Setting::query()
            ->where('key', 'site_name')
            ->where('value', 'NapCarot.com - nạp nhanh game TeaMobi, GoMobi Chiết Khấu Cao Tiện lợi, ổn định, giá tốt.')
            ->update([
                'value' => config('seo.brand_name', 'NapCarot'),
                'type' => 'string',
            ]);
    }

    /** @return array<string, SeoCategory> */
    private function seedCategories(): array
    {
        $legacyCategory = SeoCategory::query()
            ->where('slug', 'dich-vu-game-ngoc-rong-online')
            ->first();

        if ($legacyCategory instanceof SeoCategory && ! SeoCategory::query()->where('slug', 'ngoc-rong-online')->exists()) {
            foreach ($legacyCategory->posts()->get() as $post) {
                SeoRedirect::query()->updateOrCreate(
                    ['from_path' => '/dich-vu-game-ngoc-rong-online/'.$post->slug],
                    ['to_path' => '/ngoc-rong-online/'.$post->slug, 'status_code' => 301],
                );
                $this->demoteFirstHeading($post);
                $this->ensureMoneyLink($post, '/nap-game-ngoc-rong-online');
            }

            SeoRedirect::query()->updateOrCreate(
                ['from_path' => '/tin-tuc/dich-vu-game-ngoc-rong-online'],
                ['to_path' => '/tin-tuc/ngoc-rong-online', 'status_code' => 301],
            );
            $legacyCategory->update(['slug' => 'ngoc-rong-online']);
        }

        $definitions = [
            'nap-carot' => ['Nạp Carot', 'Nạp Carot: Bảng Giá Và Hướng Dẫn', 'Các nội dung về thẻ Carot, bảng giá Carot, cách nạp Carot và hướng dẫn sử dụng Carot trong game Teamobi.'],
            'ngoc-rong-online' => ['Ngọc Rồng Online', 'Hướng Dẫn Nạp Ngọc Rồng Online', 'Hướng dẫn nạp Carot, bảng giá, mức thực nhận và những lưu ý dành cho người chơi Ngọc Rồng Online.'],
            'ninja-school-online' => ['Ninja School Online', 'Hướng Dẫn Nạp Ninja School Online', 'Hướng dẫn nạp Carot, bảng giá và các thông tin cần biết khi nạp Ninja School Online.'],
            'avatar' => ['Avatar', 'Hướng Dẫn Nạp Game Avatar', 'Tổng hợp hướng dẫn nạp Carot và thông tin hỗ trợ dành cho người chơi Avatar Teamobi.'],
            'avatar-musik' => ['Avatar Musik', 'Hướng Dẫn Nạp Avatar Musik', 'Tổng hợp hướng dẫn nạp Carot và thông tin hỗ trợ dành cho người chơi Avatar Musik.'],
            'hai-tac-ti-hon' => ['Hải Tặc Tí Hon', 'Hướng Dẫn Nạp Hải Tặc Tí Hon', 'Hướng dẫn nạp Carot, bảng giá và các lưu ý dành cho người chơi Hải Tặc Tí Hon.'],
            'hiep-si-online' => ['Hiệp Sĩ Online', 'Hướng Dẫn Nạp Hiệp Sĩ Online', 'Hướng dẫn nạp Carot, bảng giá và các thông tin cần biết cho Hiệp Sĩ Online, Thời Đại Hiệp Sĩ.'],
            'huong-dan' => ['Hướng dẫn', 'Hướng Dẫn Nạp Carot Và Game Teamobi', 'Các hướng dẫn sử dụng NapCarot, nạp Carot, thanh toán và xử lý tình huống thường gặp khi nạp game.'],
        ];
        $categories = [];

        foreach ($definitions as $slug => $definition) {
            $categories[$slug] = SeoCategory::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition[0],
                    'seo_title' => $definition[1],
                    'seo_description' => $definition[2],
                    'robots' => 'index,follow',
                    'is_active' => true,
                    'sort_order' => array_search($slug, array_keys($definitions), true) + 1,
                ],
            );
        }

        return $categories;
    }

    private function migrateLegacyNgocRongCategory(SeoCategory $newCategory): void
    {
        $legacyCategory = SeoCategory::query()
            ->where('slug', 'dich-vu-game-ngoc-rong-online')
            ->first();

        if (! $legacyCategory instanceof SeoCategory) {
            return;
        }

        foreach ($legacyCategory->posts()->get() as $post) {
            SeoRedirect::query()->updateOrCreate(
                ['from_path' => '/dich-vu-game-ngoc-rong-online/'.$post->slug],
                ['to_path' => '/ngoc-rong-online/'.$post->slug, 'status_code' => 301],
            );

            $this->demoteFirstHeading($post);
            $this->ensureMoneyLink($post, '/nap-game-ngoc-rong-online');
            $post->update(['seo_category_id' => $newCategory->id]);
        }

        SeoRedirect::query()->updateOrCreate(
            ['from_path' => '/tin-tuc/dich-vu-game-ngoc-rong-online'],
            ['to_path' => '/tin-tuc/ngoc-rong-online', 'status_code' => 301],
        );
        $legacyCategory->update([
            'is_active' => false,
            'robots' => 'noindex,follow',
        ]);
    }

    private function demoteFirstHeading(SeoPost $post): void
    {
        $content = is_array($post->content) ? $post->content : [];
        $demotedHeading = false;
        $content = collect($content)->map(function (mixed $node) use (&$demotedHeading): mixed {
            if (! $demotedHeading && is_array($node) && ($node['type'] ?? null) === 'heading' && (int) ($node['level'] ?? 2) === 1) {
                $node['level'] = 2;
                $demotedHeading = true;
            }

            return $node;
        })->all();

        if ($demotedHeading) {
            $post->update(['content' => $content]);
        }
    }

    private function ensureMoneyLink(SeoPost $post, string $moneyPath): void
    {
        $freshContent = $post->fresh()->content;
        $content = is_array($freshContent) ? $freshContent : [];
        $alreadyLinked = collect($content)
            ->flatMap(fn (mixed $node): array => is_array($node['children'] ?? null) ? $node['children'] : [])
            ->contains(fn (mixed $child): bool => is_array($child) && ($child['href'] ?? null) === $moneyPath);

        if ($alreadyLinked) {
            return;
        }

        $content[] = [
            'type' => 'paragraph',
            'children' => [
                ['text' => 'Xem gói và giá đang áp dụng tại '],
                ['text' => 'trang nạp Ngọc Rồng Online', 'href' => $moneyPath],
                ['text' => '.'],
            ],
        ];
        $post->update(['content' => $content]);
    }

    private function migrateKnownNgocRongPosts(SeoCategory $newCategory): void
    {
        $communityCategory = SeoCategory::query()->where('slug', 'gia-luu')->first();

        if (! $communityCategory instanceof SeoCategory) {
            return;
        }

        $posts = $communityCategory->posts()
            ->whereIn('slug', ['cong-dong-game-ngoc-rong-online'])
            ->get();

        foreach ($posts as $post) {
            SeoRedirect::query()->updateOrCreate(
                ['from_path' => '/gia-luu/'.$post->slug],
                ['to_path' => '/ngoc-rong-online/'.$post->slug, 'status_code' => 301],
            );
            $this->demoteFirstHeading($post);
            $this->ensureMoneyLink($post, '/nap-game-ngoc-rong-online');
            $post->update(['seo_category_id' => $newCategory->id]);
        }

        if ($posts->isNotEmpty() && ! $communityCategory->posts()->exists()) {
            SeoRedirect::query()->updateOrCreate(
                ['from_path' => '/tin-tuc/gia-luu'],
                ['to_path' => '/tin-tuc/ngoc-rong-online', 'status_code' => 301],
            );
            $communityCategory->update([
                'is_active' => false,
                'robots' => 'noindex,follow',
            ]);
        }
    }

    /** @param array<string, SeoCategory> $categories */
    private function seedDraftPosts(array $categories): void
    {
        foreach ($this->postDefinitions() as $definition) {
            $post = SeoPost::query()
                ->with('category:id,slug')
                ->where('slug', $definition['slug'])
                ->orWhere('title', $definition['title'])
                ->first();
            $isNew = ! $post instanceof SeoPost;
            $post ??= new SeoPost;
            $oldSlug = $post->slug;
            $oldCategorySlug = $post->category?->slug;

            $post->fill([
                'seo_category_id' => $categories[$definition['category']]->id,
                'title' => $definition['title'],
                'slug' => $definition['slug'],
                'excerpt' => $post->excerpt ?: $definition['excerpt'],
                'seo_title' => $post->seo_title ?: $definition['title'].' | NapCarot',
                'seo_description' => $post->seo_description ?: $definition['excerpt'],
                'focus_keyword' => $post->focus_keyword ?: $definition['keyword'],
                'article_schema' => true,
                'breadcrumb_schema' => true,
            ]);

            if ($isNew) {
                $post->fill([
                    'content' => $this->skeleton($definition['title'], $definition['money_path']),
                    'canonical_url' => null,
                    'robots' => 'noindex,follow',
                    'status' => 'draft',
                    'published_at' => null,
                    'scheduled_at' => null,
                ]);
            } elseif ($post->status === 'draft') {
                $post->robots = 'noindex,follow';
                $post->published_at = null;
                $post->scheduled_at = null;

                if (empty($post->content)) {
                    $post->content = $this->skeleton($definition['title'], $definition['money_path']);
                }
            }

            $post->save();

            if ($oldSlug && $oldSlug !== $post->slug) {
                $targetPath = '/'.$definition['category'].'/'.$definition['slug'];
                $oldPaths = ['/bai-viet/'.$oldSlug, '/tin-tuc/'.$oldSlug];

                if ($oldCategorySlug) {
                    $oldPaths[] = '/'.$oldCategorySlug.'/'.$oldSlug;
                }

                if (in_array(parse_url((string) $post->canonical_url, PHP_URL_PATH), $oldPaths, true)) {
                    $post->update(['canonical_url' => null]);
                }

                foreach (array_unique($oldPaths) as $oldPath) {
                    SeoRedirect::query()->updateOrCreate(
                        ['from_path' => $oldPath],
                        ['to_path' => $targetPath, 'status_code' => 301],
                    );
                }
            }
        }
    }

    /** @return array<int, array{title: string, slug: string, keyword: string, category: string, excerpt: string, money_path: string}> */
    private function postDefinitions(): array
    {
        return [
            ['title' => 'Thẻ Carot là gì? Dùng để nạp những game nào?', 'slug' => 'the-carot-la-gi', 'keyword' => 'thẻ carot là gì', 'category' => 'nap-carot', 'excerpt' => 'Tìm hiểu thẻ Carot là gì, dùng cho game nào và những thông tin cần kiểm tra trước khi nạp Carot.', 'money_path' => '/nap-carot'],
            ['title' => 'Cách nạp Carot vào game Teamobi chi tiết từ A-Z', 'slug' => 'cach-nap-carot-game-teamobi', 'keyword' => 'cách nạp carot', 'category' => 'huong-dan', 'excerpt' => 'Hướng dẫn từng bước cách nạp Carot vào game Teamobi và kiểm tra thông tin đơn trước khi thanh toán.', 'money_path' => '/nap-game-teamobi'],
            ['title' => 'Bảng giá nạp Carot mới nhất', 'slug' => 'bang-gia-nap-carot', 'keyword' => 'bảng giá carot', 'category' => 'nap-carot', 'excerpt' => 'Cách xem bảng giá nạp Carot đang áp dụng và đối chiếu gói nạp trước khi đặt hàng.', 'money_path' => '/nap-carot'],
            ['title' => 'Nạp Carot ở đâu uy tín? Những điều cần kiểm tra trước khi nạp', 'slug' => 'nap-carot-o-dau-uy-tin', 'keyword' => 'nạp carot ở đâu', 'category' => 'nap-carot', 'excerpt' => 'Các tiêu chí cần kiểm tra khi chọn nơi nạp Carot, từ bảng giá, thông tin nhận hàng đến khả năng tra cứu đơn.', 'money_path' => '/nap-carot'],
            ['title' => 'Cách nạp Ngọc Rồng Online bằng Carot', 'slug' => 'cach-nap-ngoc-rong-online-bang-carot', 'keyword' => 'cách nạp ngọc rồng', 'category' => 'ngoc-rong-online', 'excerpt' => 'Hướng dẫn cách nạp Ngọc Rồng Online bằng Carot và các bước kiểm tra thông tin trước khi đặt hàng.', 'money_path' => '/nap-game-ngoc-rong-online'],
            ['title' => 'Bảng giá nạp Ngọc Rồng Online mới nhất', 'slug' => 'bang-gia-nap-ngoc-rong-online', 'keyword' => 'bảng giá nạp nro', 'category' => 'ngoc-rong-online', 'excerpt' => 'Hướng dẫn đọc bảng giá nạp Ngọc Rồng Online và mức thực nhận theo dữ liệu đang áp dụng.', 'money_path' => '/nap-game-ngoc-rong-online'],
            ['title' => 'Nạp đầu Ngọc Rồng Online được bao nhiêu ngọc?', 'slug' => 'nap-dau-ngoc-rong-online', 'keyword' => 'nạp đầu ngọc rồng', 'category' => 'ngoc-rong-online', 'excerpt' => 'Tìm hiểu cách kiểm tra quà và mức thực nhận khi nạp đầu Ngọc Rồng Online.', 'money_path' => '/nap-game-ngoc-rong-online'],
            ['title' => 'Nạp NRO 10K, 20K, 50K, 100K được bao nhiêu ngọc?', 'slug' => 'nap-nro-duoc-bao-nhieu-ngoc', 'keyword' => 'nạp nro được bao nhiêu ngọc', 'category' => 'ngoc-rong-online', 'excerpt' => 'Cách đối chiếu các mệnh giá NRO phổ biến với mức ngọc thực nhận đang được áp dụng.', 'money_path' => '/nap-game-ngoc-rong-online'],
            ['title' => 'Nạp x2, x3 Ngọc Rồng Online là gì?', 'slug' => 'nap-x2-x3-ngoc-rong-online', 'keyword' => 'nạp x2 ngọc rồng', 'category' => 'ngoc-rong-online', 'excerpt' => 'Giải thích cơ chế nạp x2, x3 Ngọc Rồng Online và cách kiểm tra chương trình đang áp dụng.', 'money_path' => '/nap-game-ngoc-rong-online'],
            ['title' => 'Cách nạp Ninja School Online bằng Carot', 'slug' => 'cach-nap-ninja-school-online', 'keyword' => 'nạp game ninja school', 'category' => 'ninja-school-online', 'excerpt' => 'Hướng dẫn nạp Ninja School Online bằng Carot và kiểm tra gói đang hỗ trợ.', 'money_path' => '/nap-game-ninja-school-online'],
            ['title' => 'Bảng giá nạp Ninja School Online mới nhất', 'slug' => 'bang-gia-nap-ninja-school-online', 'keyword' => 'bảng giá nso', 'category' => 'ninja-school-online', 'excerpt' => 'Cách xem bảng giá Ninja School Online theo các gói nạp đang hoạt động.', 'money_path' => '/nap-game-ninja-school-online'],
            ['title' => 'Cách nạp game Avatar Teamobi bằng Carot', 'slug' => 'cach-nap-game-avatar', 'keyword' => 'nạp game avatar', 'category' => 'avatar', 'excerpt' => 'Hướng dẫn nạp game Avatar Teamobi bằng Carot và các thông tin cần chuẩn bị.', 'money_path' => '/nap-game-avatar'],
            ['title' => 'Cách nạp Avatar Musik bằng Carot', 'slug' => 'cach-nap-avatar-musik', 'keyword' => 'nạp avatar musik', 'category' => 'avatar-musik', 'excerpt' => 'Hướng dẫn nạp Avatar Musik bằng Carot và kiểm tra gói đang được hỗ trợ.', 'money_path' => '/nap-game-avatar-musik'],
            ['title' => 'Cách nạp Hải Tặc Tí Hon bằng Carot', 'slug' => 'cach-nap-hai-tac-ti-hon', 'keyword' => 'nạp game hải tặc tí hon', 'category' => 'hai-tac-ti-hon', 'excerpt' => 'Hướng dẫn nạp Hải Tặc Tí Hon bằng Carot và theo dõi trạng thái đơn.', 'money_path' => '/nap-game-hai-tac-ti-hon'],
            ['title' => 'Bảng giá nạp Hải Tặc Tí Hon mới nhất', 'slug' => 'bang-gia-nap-hai-tac-ti-hon', 'keyword' => 'bảng giá htth', 'category' => 'hai-tac-ti-hon', 'excerpt' => 'Cách xem bảng giá Hải Tặc Tí Hon từ các gói đang hoạt động trong hệ thống.', 'money_path' => '/nap-game-hai-tac-ti-hon'],
            ['title' => 'Cách nạp Hiệp Sĩ Online bằng Carot', 'slug' => 'cach-nap-hiep-si-online', 'keyword' => 'nạp game hiệp sĩ online', 'category' => 'hiep-si-online', 'excerpt' => 'Hướng dẫn nạp Hiệp Sĩ Online bằng Carot và kiểm tra đúng thông tin nhận hàng.', 'money_path' => '/nap-game-hiep-si-online'],
            ['title' => 'Bảng giá nạp Hiệp Sĩ Online mới nhất', 'slug' => 'bang-gia-nap-hiep-si-online', 'keyword' => 'bảng giá hso', 'category' => 'hiep-si-online', 'excerpt' => 'Cách xem bảng giá Hiệp Sĩ Online theo gói nạp đang hỗ trợ.', 'money_path' => '/nap-game-hiep-si-online'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function skeleton(string $title, string $moneyPath): array
    {
        return [
            ['type' => 'heading', 'level' => 2, 'children' => [['text' => 'Tổng quan nội dung']]],
            ['type' => 'paragraph', 'children' => [['text' => "TODO: Viết phần mở đầu cho chủ đề “{$title}”, xác minh thông tin theo dữ liệu game đang áp dụng."]]],
            ['type' => 'heading', 'level' => 2, 'children' => [['text' => 'Các bước và lưu ý cần bổ sung']]],
            ['type' => 'paragraph', 'children' => [['text' => 'TODO: Bổ sung hướng dẫn, bảng dữ liệu hoặc ví dụ thực tế; không hard-code giá có thể thay đổi.']]],
            ['type' => 'heading', 'level' => 2, 'children' => [['text' => 'Xem trang nạp phù hợp']]],
            ['type' => 'paragraph', 'children' => [
                ['text' => 'Sau khi hoàn thiện nội dung, dẫn người đọc về '],
                ['text' => 'trang nạp Carot phù hợp', 'href' => $moneyPath],
                ['text' => ' để xem gói đang hoạt động và bắt đầu đặt hàng.'],
            ]],
        ];
    }
}
