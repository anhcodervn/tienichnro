<?php

use App\Features\Client\GameImage\Services\GameImageAssetService;
use App\Features\Client\GameImage\Services\GameImageOrderService;
use App\Features\Client\Wallet\Services\WalletService;
use App\Models\GameImageAccount;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function workflowAccountData(): array
{
    return ['username' => 'workflow-account', 'password' => 'private-game-password', 'server' => 'Vũ trụ 1'];
}

function workflowCapturedFiles(): array
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aJXkAAAAASUVORK5CYII=');

    return ['paths' => GameImageAssetService::PANELS,
        'files' => array_map(fn (string $name): UploadedFile => UploadedFile::fake()->createWithContent($name, $png), GameImageAssetService::PANELS)];
}

beforeEach(function (): void {
    Storage::fake('local');
    app(GameImageOrderService::class)->configure(100);
    $this->member = User::factory()->create();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->wallet = Wallet::factory()->create(['user_id' => $this->member->id, 'balance' => 1000]);
});

test('adding a game account charges the server configured price atomically and enters pending', function (): void {
    $this->actingAs($this->member)->postJson(route('tools.game-image.store'), [...workflowAccountData(), 'charged_amount' => 1, 'status' => 'success'])->assertRedirect();
    $account = GameImageAccount::query()->sole();
    expect($account->status)->toBe('pending')->and($account->charged_amount)->toBe(100)
        ->and($account->charge_transaction_id)->not->toBeNull()->and($this->wallet->fresh()->balance)->toBe(900)
        ->and(WalletTransaction::query()->where('direction', 'debit')->count())->toBe(1);
    $this->postJson(route('tools.game-image.store'), workflowAccountData())->assertUnprocessable();
    expect($this->wallet->fresh()->balance)->toBe(900)->and(GameImageAccount::query()->count())->toBe(1);
    $this->get(route('tools.game-image'))->assertOk()->assertSee('Chờ ghép')->assertSee('100 đ / tài khoản');
});

test('insufficient wallet balance rolls back the account and the debit together', function (): void {
    $this->wallet->update(['balance' => 50]);
    $this->actingAs($this->member)->postJson(route('tools.game-image.store'), workflowAccountData())->assertUnprocessable()->assertJsonValidationErrors('balance');
    expect(GameImageAccount::query()->count())->toBe(0)->and($this->wallet->fresh()->balance)->toBe(50)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

test('admin starts capture and a completed upload marks success without another wallet debit', function (): void {
    $account = app(GameImageOrderService::class)->create($this->member, workflowAccountData());
    $endpoint = route('admin.game-image.status', $account);
    $this->actingAs($this->admin)->patchJson($endpoint, ['status' => 'processing'])->assertOk()->assertJsonPath('data.status', 'processing');
    $started = $account->fresh()->processing_at;
    $this->patchJson($endpoint, ['status' => 'processing'])->assertOk();
    expect($account->fresh()->processing_at->equalTo($started))->toBeTrue();
    $this->post(route('admin.game-image.capture', $account), workflowCapturedFiles(), ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.status', 'success');
    $directory = $account->fresh()->capture_directory;
    $this->post(route('admin.game-image.capture', $account), workflowCapturedFiles(), ['Accept' => 'application/json'])->assertOk();
    expect($account->fresh()->capture_directory)->toBe($directory)->and($account->fresh()->completed_at)->not->toBeNull()
        ->and($this->wallet->fresh()->balance)->toBe(900)->and(WalletTransaction::query()->count())->toBe(1);
    $this->patchJson($endpoint, ['status' => 'error', 'error_message' => 'Late error'])->assertConflict();
    $this->patchJson($endpoint, ['status' => 'processing'])->assertConflict();
    expect($this->wallet->fresh()->balance)->toBe(900)->and($account->fresh()->status)->toBe('success');
});

test('tool errors store a safe message and refund the original price only once after prices change', function (): void {
    $account = app(GameImageOrderService::class)->create($this->member, workflowAccountData());
    app(GameImageOrderService::class)->configure(300);
    $message = 'Đăng nhập thất bại <script>alert(1)</script>';
    $endpoint = route('admin.game-image.status', $account);
    $this->actingAs($this->admin)->patchJson($endpoint, ['status' => 'processing'])->assertOk();
    $this->patchJson($endpoint, ['status' => 'error', 'error_message' => $message])->assertOk()
        ->assertJsonPath('data.status', 'error')->assertJsonPath('data.charged_amount', 100);
    $this->patchJson($endpoint, ['status' => 'error', 'error_message' => 'Duplicate'])->assertOk();
    expect($this->wallet->fresh()->balance)->toBe(1000)
        ->and(WalletTransaction::query()->where('direction', 'credit')->count())->toBe(1)
        ->and($account->fresh()->error_message)->toBe($message)->and($account->fresh()->refunded_at)->not->toBeNull();
    $this->patchJson($endpoint, ['status' => 'processing'])->assertConflict();
    $this->post(route('admin.game-image.capture', $account), workflowCapturedFiles(), ['Accept' => 'application/json'])->assertConflict();
    expect(Storage::disk('local')->allFiles('game-images'))->toBeEmpty();
    $this->actingAs($this->member)->get(route('tools.game-image', ['status' => 'error']))->assertOk()
        ->assertSee($message)->assertDontSee($message, false)->assertSee('Đã hoàn 100 đ');
});

test('errors before capture starts also refund and a failed refund cannot finalize the status', function (): void {
    $account = app(GameImageOrderService::class)->create($this->member, workflowAccountData());
    $this->mock(WalletService::class, function ($mock): void {
        $mock->shouldReceive('apply')->once()->andThrow(ValidationException::withMessages(['wallet' => 'Temporary failure']));
    });
    $this->actingAs($this->admin)->patchJson(route('admin.game-image.status', $account), ['status' => 'error', 'error_message' => 'Cannot login'])->assertUnprocessable();
    expect($account->fresh()->status)->toBe('pending')->and($account->fresh()->refunded_at)->toBeNull()
        ->and($this->wallet->fresh()->balance)->toBe(900);
    $this->forgetMock(WalletService::class);
    $this->patchJson(route('admin.game-image.status', $account), ['status' => 'error', 'error_message' => 'Cannot login'])->assertOk();
    expect($this->wallet->fresh()->balance)->toBe(1000);
});

test('members cannot trigger refunds or modify the capture price or read other peoples history', function (): void {
    $account = app(GameImageOrderService::class)->create($this->member, workflowAccountData());
    $this->getJson(route('admin.game-image.configuration'))->assertUnauthorized();
    $this->actingAs($this->member)->getJson(route('admin.game-image.history'))->assertForbidden();
    $this->putJson(route('admin.game-image.configure'), ['price' => 0])->assertForbidden();
    $this->patchJson(route('admin.game-image.status', $account), ['status' => 'error', 'error_message' => 'Fake refund'])->assertForbidden();
    expect($this->wallet->fresh()->balance)->toBe(900)->and($account->fresh()->status)->toBe('pending');
});

test('configuration is required and an explicit zero price is supported', function (): void {
    Setting::query()->where('key', 'game_image.price')->delete();
    $this->actingAs($this->admin)->getJson(route('admin.game-image.configuration'))->assertOk()->assertJsonPath('data.price', null);
    $this->actingAs($this->member)->postJson(route('tools.game-image.store'), workflowAccountData())->assertUnprocessable()->assertJsonValidationErrors('price');
    expect(GameImageAccount::query()->count())->toBe(0)->and($this->wallet->fresh()->balance)->toBe(1000);
    $this->actingAs($this->admin)->putJson(route('admin.game-image.configure'), ['price' => 0])->assertOk()->assertJsonPath('data.price', 0);
    $this->actingAs($this->member)->postJson(route('tools.game-image.store'), workflowAccountData())->assertRedirect();
    expect(GameImageAccount::query()->sole()->charged_amount)->toBe(0)->and($this->wallet->fresh()->balance)->toBe(1000);
});

test('history filters status and user with pagination and omits passwords source paths and layouts', function (): void {
    GameImageAccount::factory()->count(21)->create(['user_id' => $this->member->id, 'status' => 'processing']);
    GameImageAccount::factory()->create(['status' => 'error', 'error_message' => 'Wrong server']);
    $this->actingAs($this->admin)->getJson(route('admin.game-image.history', ['status' => 'processing', 'q' => $this->member->username]))->assertOk()
        ->assertJsonPath('meta.total', 21)->assertJsonPath('meta.per_page', 20)->assertJsonCount(20, 'data')
        ->assertJsonPath('data.0.status', 'processing')->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.layout')
        ->assertJsonMissingPath('data.0.capture_directory');
    $this->getJson(route('admin.game-image.history', ['status' => 'error']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.error_message', 'Wrong server');
});

test('status callbacks require an error message and success can only come from a valid upload', function (): void {
    $account = app(GameImageOrderService::class)->create($this->member, workflowAccountData());
    $this->actingAs($this->admin)->patchJson(route('admin.game-image.status', $account), ['status' => 'error'])->assertUnprocessable();
    $this->patchJson(route('admin.game-image.status', $account), ['status' => 'success'])->assertUnprocessable();
    $this->postJson(route('admin.game-image.capture', $account), [])->assertUnprocessable();
    expect($account->fresh()->status)->toBe('pending')->and($this->wallet->fresh()->balance)->toBe(900);
});

test('invalid configuration prices never change the existing price', function (mixed $price): void {
    $this->actingAs($this->admin)->putJson(route('admin.game-image.configure'), ['price' => $price])->assertUnprocessable();
    expect(app(GameImageOrderService::class)->configuration()['price'])->toBe(100);
})->with([-1, 1.5, 100000001, null, 'invalid']);

test('recapture charges the quoted current fee once and rejects old callbacks and replays', function (): void {
    $orders = app(GameImageOrderService::class);
    $account = $orders->create($this->member, workflowAccountData());
    $orders->transition($account, 'error', 'First capture failed');
    $orders->configure(150);
    $url = route('tools.game-image.recapture', $account);
    $this->actingAs($this->member)->postJson($url, ['capture_version' => 1, 'price' => 150])->assertOk();
    expect($account->fresh()->capture_version)->toBe(2)->and($account->fresh()->status)->toBe('pending')
        ->and($account->fresh()->refunded_at)->toBeNull()->and($account->fresh()->error_message)->toBeNull()
        ->and($this->wallet->fresh()->balance)->toBe(850);
    $this->postJson($url, ['capture_version' => 1, 'price' => 150])->assertConflict();
    $this->actingAs($this->admin)->patchJson(route('admin.game-image.status', $account), ['status' => 'error', 'error_message' => 'Late old callback'])->assertConflict();
    $this->postJson(route('admin.game-image.capture', $account), workflowCapturedFiles())->assertConflict();
    expect($account->fresh()->status)->toBe('pending')->and(Storage::disk('local')->allFiles())->toBe([]);
    $this->patchJson(route('admin.game-image.status', $account), ['status' => 'processing', 'capture_version' => 2])->assertOk()->assertJsonPath('data.capture_version', 2);
    $this->postJson(route('admin.game-image.capture', $account), [...workflowCapturedFiles(), 'capture_version' => 2])->assertOk();
    $this->actingAs($this->member)->postJson($url, ['capture_version' => 1, 'price' => 150])->assertConflict();
    expect($this->wallet->fresh()->balance)->toBe(850)->and($account->fresh()->status)->toBe('success')
        ->and(WalletTransaction::query()->where('direction', 'debit')->count())->toBe(2);
});

test('recapture errors refund the new charge once and preserve the previous images', function (): void {
    $orders = app(GameImageOrderService::class);
    $account = $orders->create($this->member, workflowAccountData());
    app(GameImageAssetService::class)->storeCapture($account, workflowCapturedFiles()['files'], GameImageAssetService::PANELS);
    $directory = $account->capture_directory;
    $orders->configure(200);
    $this->actingAs($this->member)->get(route('tools.game-image'))->assertOk()->assertSee('data-game-image-recapture', false)->assertSee('Chụp lại');
    $this->postJson(route('tools.game-image.recapture', $account), ['capture_version' => 1, 'price' => 200])->assertOk();
    expect($this->wallet->fresh()->balance)->toBe(700)->and($account->fresh()->capture_directory)->toBe($directory);
    $orders->configure(300);
    $payload = ['status' => 'error', 'error_message' => 'Capture failed again', 'capture_version' => 2];
    $this->actingAs($this->admin)->patchJson(route('admin.game-image.status', $account), $payload)->assertOk();
    $this->patchJson(route('admin.game-image.status', $account), $payload)->assertOk();
    expect($this->wallet->fresh()->balance)->toBe(900)->and($account->fresh()->capture_directory)->toBe($directory)
        ->and(Storage::disk('local')->exists($directory.'/panel-quest.png'))->toBeTrue();
    $this->actingAs($this->member)->postJson(route('tools.game-image.recapture', $account), ['capture_version' => 2, 'price' => 300])->assertOk();
    $this->actingAs($this->admin)->patchJson(route('admin.game-image.status', $account), [...$payload, 'capture_version' => 3])->assertOk();
    expect($this->wallet->fresh()->balance)->toBe(900)->and(WalletTransaction::query()->where('direction', 'credit')->count())->toBe(2);
});

test('recapture rejects changed fees insufficient funds pending accounts and other owners', function (): void {
    $orders = app(GameImageOrderService::class);
    $account = $orders->create($this->member, workflowAccountData());
    $url = route('tools.game-image.recapture', $account);
    $payload = ['capture_version' => 1, 'price' => 100];
    $this->actingAs($this->member)->postJson($url, $payload)->assertConflict();
    $orders->transition($account, 'error', 'Failed');
    $this->postJson($url, [...$payload, 'price' => 99])->assertUnprocessable()->assertJsonValidationErrors('price');
    $this->wallet->update(['balance' => 50]);
    $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('balance');
    $this->actingAs(User::factory()->create())->postJson($url, $payload)->assertForbidden();
    expect($account->fresh()->capture_version)->toBe(1)->and($account->fresh()->status)->toBe('error')
        ->and($this->wallet->fresh()->balance)->toBe(50)->and(WalletTransaction::query()->count())->toBe(2);
});

test('private recapture requests are not throttled and still enforce capture state', function (): void {
    $account = app(GameImageOrderService::class)->create($this->member, workflowAccountData());
    $this->actingAs($this->member);
    for ($attempt = 0; $attempt < 15; $attempt++) {
        $this->postJson(route('tools.game-image.recapture', $account), ['capture_version' => 1, 'price' => 100])->assertConflict();
    }
    expect($this->wallet->fresh()->balance)->toBe(900)->and($account->fresh()->capture_version)->toBe(1);
});

test('private image routes retain authentication without request throttles', function (): void {
    foreach (app('router')->getRoutes() as $route) {
        $name = $route->getName() ?? '';
        if (! str_starts_with($name, 'api.game-image.') && ! str_starts_with($name, 'admin.game-image.') &&
            (! str_starts_with($name, 'tools.game-image.') || str_starts_with($name, 'tools.game-image.shared'))) {
            continue;
        }
        $middleware = $route->gatherMiddleware();
        expect(collect($middleware)->contains(fn (string $item): bool => str_starts_with($item, 'throttle:')))->toBeFalse()
            ->and(collect($middleware)->contains(fn (string $item): bool => $item === 'auth' || $item === 'auth:sanctum'))->toBeTrue();
    }
    expect(app('router')->getRoutes()->getByName('tools.game-image.shared')->gatherMiddleware())->toContain('throttle:60,1');
});
