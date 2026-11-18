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
            $sources = DB::table('servers')->where('type', 'napcarot')->orderBy('id')->get();
            foreach ($sources as $source) {
                $target = DB::table('servers')->where('type', 'game')->where('server_code', $source->server_code)->first();
                if ($target === null) {
                    if (DB::table('notifies')->where('server_id', $source->id)->exists()
                        || DB::table('nro_event_receipts')->where('server_id', $source->id)->exists()) {
                        throw new RuntimeException('Missing game server for notification history: '.$source->server_code);
                    }

                    continue;
                }

                $hasConflict = DB::table('nro_event_receipts')->where('server_id', $target->id)
                    ->whereIn('event_key', DB::table('nro_event_receipts')->select('event_key')->where('server_id', $source->id))->exists();
                if ($hasConflict) {
                    throw new RuntimeException('Conflicting game notification receipts for server: '.$source->server_code);
                }

                DB::table('notifies')->where('server_id', $source->id)->update(['server_id' => $target->id]);
                DB::table('nro_event_receipts')->where('server_id', $source->id)->update(['server_id' => $target->id]);
                if ($target->last_ping_at === null && $source->last_ping_at !== null) {
                    DB::table('servers')->where('id', $target->id)->update([
                        'connection' => $source->connection,
                        'last_ping_at' => $source->last_ping_at,
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
        // Keep notification references in their game catalog when rolling back.
    }
};
