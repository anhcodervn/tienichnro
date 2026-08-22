<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class GameServerSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TopupCatalogSeeder::class);
    }
}
