<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $originals = DB::table('servers')->where('type', 'napcarot')->orderBy('id')->get();
            foreach (['web', 'game'] as $type) {
                foreach ($originals as $server) {
                    DB::table('servers')->insertOrIgnore([
                        'type' => $type, 'server_code' => $server->server_code, 'code' => $server->code,
                        'name' => $server->name, 'is_active' => $server->is_active, 'sort_order' => $server->sort_order,
                        'connection' => false, 'last_ping_at' => null, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('servers')->whereIn('type', ['web', 'game'])->delete();
    }
};
