<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('type_notifies', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('code', 64)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });
        Schema::create('code_notifies', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('type_id');
            $table->foreign('type_id')->references('id')->on('type_notifies')->restrictOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });
        Schema::create('bosses', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('code', 64)->unique('uq_bosses_code');
            $table->string('name', 100)->index('idx_bosses_name');
            $table->unsignedInteger('respawn_seconds')->nullable();
            $table->boolean('is_active')->default(true)->index('idx_bosses_active');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
        Schema::create('notifies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('server_id')->index();
            $table->unsignedInteger('code_id')->index();
            $table->unsignedInteger('boss_id')->nullable()->index();
            $table->foreign('server_id')->references('id')->on('servers')->restrictOnDelete();
            $table->foreign('code_id')->references('id')->on('code_notifies')->restrictOnDelete();
            $table->foreign('boss_id')->references('id')->on('bosses')->restrictOnDelete();
            $table->string('char_name', 100)->nullable();
            $table->text('content');
            $table->text('death_content')->nullable();
            $table->string('map_name', 150)->nullable();
            $table->unsignedInteger('map_id')->nullable();
            $table->unsignedSmallInteger('zone')->nullable();
            $table->dateTime('time_start', 6)->index();
            $table->dateTime('expires_at', 6)->nullable();
            $table->dateTime('death_time', 6)->nullable()->index();
            $table->string('killed_by', 100)->nullable();
            $table->dateTime('respawn_at', 6)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['server_id', 'boss_id', 'death_time', 'time_start'], 'notifies_living_boss_index');
            $table->index(['boss_id', 'respawn_at'], 'notifies_respawn_index');
        });
        Schema::create('nro_event_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->restrictOnDelete();
            $table->string('event_key', 64);
            $table->string('payload_hash', 64);
            $table->foreignId('notify_id')->nullable()->constrained('notifies')->nullOnDelete();
            $table->string('status', 40);
            $table->text('content');
            $table->dateTime('occurred_at', 6);
            $table->timestamps();
            $table->unique(['server_id', 'event_key']);
            $table->index(['server_id', 'payload_hash']);
        });
    }

    public function down(): void
    {
        foreach (['nro_event_receipts', 'notifies', 'bosses', 'code_notifies', 'type_notifies', 'servers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
