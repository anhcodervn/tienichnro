<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_offerings', function (Blueprint $table) {
            $table->string('page_slug', 120)->nullable()->index();
        });
        DB::table('service_offerings')->orderBy('id')->chunkById(100, function ($services): void {
            foreach ($services as $service) {
                $slug = trim((string) parse_url($service->url ?? '', PHP_URL_PATH), '/');
                if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) && strlen($slug) <= 120) {
                    DB::table('service_offerings')->where('id', $service->id)->update(['page_slug' => $slug]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_offerings', function (Blueprint $table) {
            $table->dropColumn('page_slug');
        });
    }
};
