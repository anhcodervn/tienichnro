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
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
            $table->dropUnique('users_email_unique');
            $table->dropUnique('users_phone_unique');

            $table->unique(['tenant_id', 'username'], 'users_tenant_username_unique');
            $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');
            $table->unique(['tenant_id', 'phone'], 'users_tenant_phone_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_username_unique');
            $table->dropUnique('users_tenant_email_unique');
            $table->dropUnique('users_tenant_phone_unique');

            $table->unique('username');
            $table->unique('email');
            $table->unique('phone');
        });
    }
};
