<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TopupCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            ['name' => 'Ngọc Rồng Online', 'short_name' => 'NRO'],
            ['name' => 'Ninja School Online', 'short_name' => 'NSO'],
            ['name' => 'Hiệp Sĩ Online', 'short_name' => 'HSO'],
            ['name' => 'Avatar', 'short_name' => 'AVATAR'],
            ['name' => 'Mobi Army', 'short_name' => 'MARMY'],
        ];

        foreach ($games as $gameIndex => $data) {
            $game = Game::query()->updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [...$data, 'description' => 'Nạp Carot nhanh, minh bạch và hỗ trợ tận tâm.', 'status' => 'active', 'sort_order' => $gameIndex],
            );

            $server = $game->servers()->updateOrCreate(
                ['name' => 'Máy chủ mặc định'],
                ['code' => 'DEFAULT', 'status' => 'active', 'sort_order' => 0],
            );

            if ($game->slug === 'ngoc-rong-online') {
                continue;
            }

            foreach ([100 => 90000, 500 => 430000, 1000 => 830000, 2000 => 1600000] as $carot => $price) {
                $game->packages()->updateOrCreate(
                    ['game_server_id' => $server->id, 'carot_amount' => $carot],
                    [
                        'name' => number_format($carot, 0, ',', '.').' Carot',
                        'provider_price' => $price - ($carot * 50),
                        'price' => $price,
                        'original_price' => $carot * 1000,
                        'status' => 'active',
                        'sort_order' => $carot,
                    ],
                );
            }
        }
    }
}
