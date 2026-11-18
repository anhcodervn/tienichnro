<?php

namespace Database\Seeders;

use App\Models\ManagedServer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ManagedServerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $servers = [
            [1, '1 sao', '1'], [2, '2 sao', '2'], [3, '3 sao', '3'], [4, '4 sao', '6'],
            [5, '5 sao', '7'], [6, '6 sao', '9'], [7, '7 sao', '10'], [8, '8 sao', '11'],
            [9, '9 sao', '12'], [10, '10 sao', '13'], [11, '12 sao', '15'], [12, '13 sao', '18'],
            [13, '14 sao', '20'], [19, '15 sao', '22'], [14, '11 sao (vip 1)', '14'],
            [15, 'VIP 2', '19'], [16, 'super 1', '16'], [17, 'super 2', '17'], [18, 'super 3', '21'],
        ];
        DB::transaction(function () use ($servers): void {
            foreach (ManagedServer::TYPES as $type) {
                if (ManagedServer::query()->where('type', $type)->exists()) {
                    continue;
                }
                foreach ($servers as $index => [$providerId, $name, $loginId]) {
                    ManagedServer::query()->create(['type' => $type, 'server_id' => (string) ($type === 'carot' ? $providerId : $loginId),
                        'name' => $name, 'is_active' => true, 'sort_order' => $index]);
                }
            }
        });
    }
}
