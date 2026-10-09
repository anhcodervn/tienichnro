<?php

namespace Database\Seeders;

use App\Models\SeoCategory;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class ArticleWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'tien-ich' => 'Tiện ích NRO',
            'huong-dan' => 'Hướng dẫn Ngọc Rồng',
            'kinh-nghiem' => 'Kinh nghiệm chơi',
            'tin-tuc' => 'Tin tức Ngọc Rồng Online',
        ];

        foreach ($categories as $slug => $name) {
            SeoCategory::query()->firstOrCreate(['slug' => $slug], [
                'name' => $name, 'robots' => 'index,follow', 'is_active' => true,
            ]);
        }

        $settings = [
            'site_name' => config('app.name', 'Tiện ích NRO'),
            'site_description' => config('seo.homepage.description'),
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value, 'type' => 'string']);
        }
    }
}
