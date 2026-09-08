<?php

use App\Http\Middleware\CaptureAffiliateReferral;
use App\Jobs\SaveUserLogJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::dropIfExists('wallets');
    Schema::dropIfExists('users');

    Schema::create('users', function ($table): void {
        $table->id();
        $table->string('username')->unique();
        $table->string('email')->nullable()->unique();
        $table->string('phone')->nullable()->unique();
        $table->string('full_name')->nullable();
        $table->string('avatar')->nullable();
        $table->string('google_id')->nullable()->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->string('role')->default('user');
        $table->string('status')->default('active');
        $table->timestamp('last_login_at')->nullable();
        $table->string('last_login_ip', 45)->nullable();
        $table->string('referral_code')->nullable()->unique();
        $table->unsignedBigInteger('referred_by')->nullable();
        $table->rememberToken();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('wallets', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('type')->default('main');
        $table->decimal('balance', 16, 2)->default(0);
        $table->decimal('hold_balance', 16, 2)->default(0);
        $table->decimal('total_recharge', 16, 2)->default(0);
        $table->decimal('total_spent', 16, 2)->default(0);
        $table->timestamps();
    });
});

test('authenticated user can logout through json endpoint', function () {
    Queue::fake();
    $this->withoutMiddleware(CaptureAffiliateReferral::class);

    $user = User::factory()->create([
        'username' => 'demo_user',
        'email' => 'demo@example.com',
    ]);

    $this->actingAs($user);

    $response = $this->postJson(route('logout'));

    $response
        ->assertOk()
        ->assertJson([
            'status' => true,
            'message' => 'Đăng xuất thành công.',
            'redirect' => route('login'),
        ]);

    $this->assertGuest();

    Queue::assertPushed(SaveUserLogJob::class, fn (SaveUserLogJob $job): bool => $job->userId === $user->id && $job->action === 'logout');
});
