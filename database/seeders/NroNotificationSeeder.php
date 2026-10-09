<?php

namespace Database\Seeders;

use App\Models\CodeNotify;
use App\Models\NroServer;
use App\Models\TypeNotify;
use Illuminate\Database\Seeder;

class NroNotificationSeeder extends Seeder
{
    public function run(): void
    {
        $names = ['Vũ trụ 1', 'Vũ trụ 2', 'Vũ trụ 3', 'Vũ trụ 4', 'Vũ trụ 5', 'Vũ trụ 6', 'Vũ trụ 7', 'Vũ trụ 8', 'Vũ trụ 9', 'Vũ trụ 10', 'Vũ trụ 11', 'Vũ trụ 12', 'Võ đài liên vũ trụ', 'Universe 1', 'Naga', 'Super 1', 'Super 2', 'Vũ trụ 13', 'VIP 2', 'Vũ trụ 14', 'Vũ trụ 15', 'Super 3'];
        if (! NroServer::query()->exists()) {
            foreach ($names as $index => $name) {
                NroServer::query()->create(['server_code' => $index + 1, 'code' => 'sv'.($index + 1), 'name' => $name, 'is_active' => true, 'sort_order' => $index + 1]);
            }
        }
        $bossType = TypeNotify::query()->firstOrCreate(['code' => 'boss'], ['name' => 'Boss']);
        $generalType = TypeNotify::query()->firstOrCreate(['code' => 'general'], ['name' => 'Thông báo game']);
        CodeNotify::withTrashed()->firstOrCreate(['system_key' => 'BOSS'], ['code' => 'BOSS', 'name' => 'Boss', 'type_id' => $bossType->id, 'additional_filters' => ['boss', 'state'], 'keywords' => ['vừa xuất hiện tại', 'vừa bị tiêu diệt bởi']]);
        foreach (['SET_ACTIVATION', 'MAINTENANCE', 'CRYSTAL_UPGRADE', 'ITEM_UPGRADE', 'GOD_ITEM', 'PERMANENT_ITEM', 'OTHER'] as $code) {
            CodeNotify::withTrashed()->firstOrCreate(['system_key' => $code], ['code' => $code, 'name' => $code, 'type_id' => $generalType->id]);
        }
    }
}
