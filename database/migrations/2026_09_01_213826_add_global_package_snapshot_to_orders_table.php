<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('package_source', 20)->default('custom')->after('topup_package_id');
            $table->foreignId('global_topup_package_id')->nullable()->after('package_source')->constrained()->nullOnDelete();
            $table->string('global_topup_package_name')->nullable()->after('global_topup_package_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('global_topup_package_id');
            $table->dropColumn(['package_source', 'global_topup_package_name']);
        });
    }
};
