<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupArticleWebsite extends Command
{
    protected $signature = 'site:setup {--database= : Database connection name} {--force : Run migrations in production}';

    protected $description = 'Initialize the article website without running legacy commerce migrations';

    public function handle(): int
    {
        $connection = $this->option('database') ?: config('database.default');
        $this->info('Database: '.DB::connection($connection)->getDatabaseName());

        foreach ([
            '2026_10_06_103108_ensure_article_website_authentication_tables.php',
            '2026_10_06_093807_prepare_article_website_tables.php',
            '2026_10_06_104325_create_nro_notification_tables.php',
            '2026_10_08_091639_add_server_code_to_servers_table.php',
            '2026_10_08_091659_backfill_server_codes.php',
            '2026_10_08_091659_require_unique_server_codes.php',
            '2026_10_08_113205_add_code_and_sort_order_to_servers.php',
            '2026_10_08_113205_populate_default_nro_servers.php',
            '2026_10_08_103821_ensure_article_website_supporting_tables.php',
            '2026_10_08_100604_remove_empty_affiliate_and_collaborator_tables.php',
            '2026_10_08_101442_remove_empty_legacy_game_catalog_tables.php',
            '2026_10_08_102136_remove_empty_legacy_global_topup_prices.php',
            '2026_10_08_102528_retire_legacy_commerce_tables.php',
            '2026_10_08_104336_remove_tenancy_and_support.php',
            '2026_10_08_121859_add_additional_filters_to_code_notifies.php',
            '2026_10_08_123043_add_keywords_to_code_notifies.php',
            '2026_10_08_123829_make_notification_types_editable_and_deletable.php',
            '2026_10_08_143148_add_boss_name_to_notifies_table.php',
            '2026_10_08_143225_backfill_notification_boss_names.php',
            '2026_10_08_144352_add_zone_name_to_notifies_table.php',
            '2026_10_08_150130_add_game_names_to_bosses_table.php',
            '2026_10_08_150553_add_soft_deletes_to_bosses_table.php',
            '2026_10_08_151439_add_is_boss_to_notifies_table.php',
            '2026_10_08_153412_add_sort_order_to_bosses_table.php',
        ] as $migration) {
            $result = $this->call('migrate', [
                '--database' => $connection,
                '--path' => 'database/migrations/'.$migration,
                '--force' => (bool) $this->option('force'),
                '--no-interaction' => true,
            ]);

            if ($result !== self::SUCCESS) {
                return $result;
            }
        }

        $result = $this->call('db:seed', [
            '--database' => $connection,
            '--class' => DatabaseSeeder::class,
            '--force' => (bool) $this->option('force'),
            '--no-interaction' => true,
        ]);

        if ($result === self::SUCCESS) {
            $this->info('Article website ready. Existing content and accounts are preserved.');
        }

        return $result;
    }
}
