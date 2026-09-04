<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->where(fn ($query) => $query->whereNull('referral_code')->orWhere('referral_code', ''))
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    do {
                        $referralCode = Str::upper(Str::random(10));
                    } while (DB::table('users')->where('referral_code', $referralCode)->exists());

                    DB::table('users')
                        ->where('id', $user->id)
                        ->where(fn ($query) => $query->whereNull('referral_code')->orWhere('referral_code', ''))
                        ->update(['referral_code' => $referralCode]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
