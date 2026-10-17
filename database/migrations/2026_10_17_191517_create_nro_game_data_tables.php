<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nro_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_map');
            $table->string('name', 255);
            $table->unique(['id_map']);
            $table->string('icon', 2048)->nullable();
            $table->string('image', 2048)->nullable();
            $table->char('content_hash', 64);
            $table->json('source');
            $table->timestamps();
        });

        Schema::create('nro_npcs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_npc');
            $table->string('name', 255);
            $table->integer('head_id');
            $table->integer('body_id');
            $table->integer('leg_id');
            $table->json('menu');
            $table->unique(['id_npc']);
            $table->string('icon', 2048)->nullable();
            $table->string('image', 2048)->nullable();
            $table->char('content_hash', 64);
            $table->json('source');
            $table->timestamps();
        });

        Schema::create('nro_mobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_mob');
            $table->integer('type');
            $table->string('name', 255);
            $table->unsignedBigInteger('hp');
            $table->integer('range_move');
            $table->integer('speed');
            $table->integer('dart_type');
            $table->unique(['id_mob']);
            $table->string('icon', 2048)->nullable();
            $table->string('image', 2048)->nullable();
            $table->char('content_hash', 64);
            $table->json('source');
            $table->timestamps();
        });

        Schema::create('nro_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_item');
            $table->integer('type');
            $table->integer('gender');
            $table->string('name', 255);
            $table->text('description');
            $table->integer('level');
            $table->unsignedBigInteger('str_require');
            $table->integer('icon_id');
            $table->integer('part');
            $table->boolean('is_up_to_up');
            $table->unique(['id_item']);
            $table->string('icon', 2048)->nullable();
            $table->string('image', 2048)->nullable();
            $table->char('content_hash', 64);
            $table->json('source');
            $table->timestamps();
        });

        Schema::create('nro_options', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);
            $table->unsignedInteger('id_option');
            $table->string('name', 255);
            $table->integer('type')->nullable();
            $table->integer('color')->nullable();
            $table->unique(['kind', 'id_option']);
            $table->string('icon', 2048)->nullable();
            $table->string('image', 2048)->nullable();
            $table->char('content_hash', 64);
            $table->json('source');
            $table->timestamps();
        });

        Schema::create('nro_skills', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('class_id');
            $table->string('class_name', 255);
            $table->unsignedInteger('id_skill');
            $table->string('name', 255);
            $table->integer('max_point');
            $table->integer('mana_use_type');
            $table->integer('type');
            $table->integer('icon_id');
            $table->text('dam_info');
            $table->text('description');
            $table->json('levels');
            $table->unique(['class_id', 'id_skill']);
            $table->string('icon', 2048)->nullable();
            $table->string('image', 2048)->nullable();
            $table->char('content_hash', 64);
            $table->json('source');
            $table->timestamps();
        });

        Schema::create('nro_game_data_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('dataset', 32);
            $table->string('status', 16)->default('queued');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedInteger('server_code')->nullable();
            $table->unsignedInteger('source_version')->nullable();
            $table->dateTime('source_updated_at', 6)->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('unchanged')->default(0);
            $table->unsignedInteger('total_chunks')->default(0);
            $table->json('completed_chunks')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['dataset', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nro_game_data_syncs');
        Schema::dropIfExists('nro_skills');
        Schema::dropIfExists('nro_options');
        Schema::dropIfExists('nro_items');
        Schema::dropIfExists('nro_mobs');
        Schema::dropIfExists('nro_npcs');
        Schema::dropIfExists('nro_maps');
    }
};
