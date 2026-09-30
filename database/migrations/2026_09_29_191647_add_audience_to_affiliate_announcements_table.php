<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('affiliate_announcements', function (Blueprint $table): void {
            $table->index('tenant_id', 'affiliate_announcements_tenant_migration_index');
        });
        Schema::table('affiliate_announcements', function (Blueprint $table): void {
            $table->dropIndex('affiliate_announcements_feed_index');
        });
        Schema::table('affiliate_announcements', function (Blueprint $table): void {
            $table->string('audience', 30)->default('affiliate')->after('admin_id');
            $table->index(
                ['tenant_id', 'audience', 'is_published', 'is_pinned', 'published_at'],
                'affiliate_announcements_feed_index',
            );
        });
        Schema::table('affiliate_announcements', function (Blueprint $table): void {
            $table->dropIndex('affiliate_announcements_tenant_migration_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliate_announcements', function (Blueprint $table): void {
            $table->index('tenant_id', 'affiliate_announcements_tenant_migration_index');
        });
        Schema::table('affiliate_announcements', function (Blueprint $table): void {
            $table->dropIndex('affiliate_announcements_feed_index');
        });
        Schema::table('affiliate_announcements', function (Blueprint $table): void {
            $table->dropColumn('audience');
            $table->index(
                ['tenant_id', 'is_published', 'is_pinned', 'published_at'],
                'affiliate_announcements_feed_index',
            );
        });
        Schema::table('affiliate_announcements', function (Blueprint $table): void {
            $table->dropIndex('affiliate_announcements_tenant_migration_index');
        });
    }
};
