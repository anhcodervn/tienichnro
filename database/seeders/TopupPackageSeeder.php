<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TopupPackageSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TopupCatalogSeeder::class);
    }
}
