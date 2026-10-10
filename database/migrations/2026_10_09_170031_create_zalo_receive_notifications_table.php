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
        Schema::create('zalo_receive_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('box_zalo_id', 128)->nullable()->index();
            $table->string('zalo_id', 128)->index();
            $table->string('char_name', 100)->index();
            $table->string('type_receive', 1000);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zalo_receive_notifications');
    }
};
