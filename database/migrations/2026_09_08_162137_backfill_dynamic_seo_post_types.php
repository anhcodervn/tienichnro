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
        DB::table('seo_posts')
            ->whereIn('slug', $this->guideSlugs())
            ->update(['type' => 'guide', 'service_id' => null]);

        foreach ($this->priceServiceSlugs() as $postSlug => $serviceSlugs) {
            $serviceId = DB::table('games')->whereIn('slug', $serviceSlugs)->value('id');

            if ($serviceId === null) {
                continue;
            }

            DB::table('seo_posts')
                ->where('slug', $postSlug)
                ->update(['type' => 'price', 'service_id' => $serviceId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('seo_posts')
            ->whereIn('slug', [...$this->guideSlugs(), ...array_keys($this->priceServiceSlugs())])
            ->update(['type' => 'knowledge', 'service_id' => null]);
    }

    /** @return array<int, string> */
    private function guideSlugs(): array
    {
        return [
            'cach-nap-carot-game-teamobi',
            'cach-nap-ngoc-rong-online-bang-carot',
            'cach-nap-ninja-school-online',
            'cach-nap-game-avatar',
            'cach-nap-avatar-musik',
            'cach-nap-hai-tac-ti-hon',
            'cach-nap-hiep-si-online',
        ];
    }

    /** @return array<string, array<int, string>> */
    private function priceServiceSlugs(): array
    {
        return [
            'bang-gia-nap-ngoc-rong-online' => ['ngoc-rong-online'],
            'bang-gia-nap-ninja-school-online' => ['ninja-school-online', 'ninja-school'],
            'bang-gia-nap-hai-tac-ti-hon' => ['hai-tac-ti-hon'],
            'bang-gia-nap-hiep-si-online' => ['hiep-si-online'],
        ];
    }
};
