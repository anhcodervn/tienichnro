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
        $names = ['Vũ trụ 1', 'Vũ trụ 2', 'Vũ trụ 3', 'Vũ trụ 4', 'Vũ trụ 5', 'Vũ trụ 6', 'Vũ trụ 7', 'Vũ trụ 8', 'Vũ trụ 9', 'Vũ trụ 10', 'Vũ trụ 11', 'Vũ trụ 12', 'Võ đài liên vũ trụ', 'Universe 1', 'Naga', 'Super 1', 'Super 2', 'Vũ trụ 13', 'VIP 2', 'Vũ trụ 14', 'Vũ trụ 15', 'Super 3'];
        $wasEmpty = DB::transaction(function () use ($names): bool {
            $wasEmpty = ! DB::table('servers')->exists();
            foreach ($names as $index => $name) {
                $number = $index + 1;
                if (DB::table('servers')->where('code', 'sv'.$number)->where('server_code', '!=', $number)->exists()) {
                    throw new RuntimeException('Server code sv'.$number.' belongs to another server.');
                }
            }
            foreach ($names as $index => $name) {
                $number = $index + 1;
                $attributes = ['code' => 'sv'.$number, 'name' => $name, 'is_active' => true, 'sort_order' => $number, 'updated_at' => now()];
                $existing = DB::table('servers')->where('server_code', $number)->first();
                if ($existing !== null) {
                    DB::table('servers')->where('id', $existing->id)->update($attributes);
                } else {
                    $attributes['server_code'] = $number;
                    $attributes['created_at'] = now();
                    if ($wasEmpty) {
                        $attributes['id'] = $number;
                    }
                    DB::table('servers')->insert($attributes);
                }
            }

            return $wasEmpty;
        });
        if ($wasEmpty && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `servers` AUTO_INCREMENT = 23');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Preserve servers referenced by notification history.
    }
};
