<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ManagedServerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $originals = DB::table('servers')->where('type', 'napcarot')->orderBy('id')->get();
            foreach (['web', 'game'] as $type) {
                if (DB::table('servers')->where('type', $type)->exists()) {
                    continue;
                }
                foreach ($originals as $server) {
                    DB::table('servers')->insertOrIgnore(['type' => $type, 'server_code' => $server->server_code, 'code' => $server->code,
                        'name' => $server->name, 'is_active' => $server->is_active, 'sort_order' => $server->sort_order,
                        'connection' => false, 'last_ping_at' => null, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }
}
