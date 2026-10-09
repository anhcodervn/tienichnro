<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'user_sessions' => '2026_05_19_013647_create_user_sessions_table.php',
        ];

        foreach ($tables as $table => $file) {
            if (! Schema::hasTable($table)) {
                (require database_path('migrations/'.$file))->up();
            }
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('scope', 30)->default('user');
                $table->string('title');
                $table->text('content');
                $table->string('redirect_url', 2048)->nullable();
                $table->string('type')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamps();
                $table->index('created_at');
                $table->index(['user_id', 'is_read']);
                $table->index(['scope', 'created_at'], 'notifications_scope_created_at_index');
                $table->index(['scope', 'user_id'], 'notifications_scope_user_id_index');
            });
        }

        if (! Schema::hasTable('notification_reads')) {
            (require database_path('migrations/2026_05_28_150943_create_notification_reads_table.php'))->up();
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Supporting tables may contain existing accounts and messages; restore from backup to reverse this setup.');
    }
};
