<?php

use Database\Seeders\CaptchaProviderSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new CaptchaProviderSeeder)->run();
    }

    /** Provider configuration may have been edited; schema rollback owns its removal. */
    public function down(): void {}
};
