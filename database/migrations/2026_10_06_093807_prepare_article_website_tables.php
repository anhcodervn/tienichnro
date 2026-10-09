<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'cache' => '0001_01_01_000001_create_cache_table.php',
            'jobs' => '0001_01_01_000002_create_jobs_table.php',
            'personal_access_tokens' => '2026_05_18_074710_create_personal_access_tokens_table.php',
            'settings' => '2026_05_19_013663_create_settings_table.php',
            'user_logs' => '2026_05_19_013657_create_user_logs_table.php',
            'queue_logs' => '2026_05_29_015030_create_queue_logs_table.php',
            'contact_feedbacks' => '2026_05_29_020940_create_contact_feedback_table.php',
            'seo_categories' => '2026_06_02_120000_create_seo_categories_table.php',
            'seo_redirects' => '2026_09_08_101708_create_seo_redirects_table.php',
        ];

        foreach ($tables as $table => $file) {
            if (! Schema::hasTable($table)) {
                (require database_path('migrations/'.$file))->up();
            }
        }

        if (! Schema::hasTable('seo_posts')) {
            (require database_path('migrations/2026_06_02_120100_create_seo_posts_table.php'))->up();
            Schema::table('seo_posts', function (Blueprint $table): void {
                $table->string('type')->default('knowledge')->index();
                $table->unsignedBigInteger('service_id')->nullable();
                $table->json('faq')->nullable();
                $table->string('cover_image', 2048)->nullable();
                $table->text('meta_keywords')->nullable();
                $table->index(['status', 'published_at']);
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('admin_audit_logs')) {
            (require database_path('migrations/2026_08_20_105842_create_admin_audit_logs_table.php'))->up();
            Schema::table('admin_audit_logs', function (Blueprint $table): void {
                $table->uuid('request_id')->nullable();
                $table->string('route_name')->nullable();
                $table->string('method', 10)->nullable();
                $table->string('path', 1000)->nullable();
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('This additive legacy database bootstrap cannot be rolled back without reviewing existing content.');
    }
};
