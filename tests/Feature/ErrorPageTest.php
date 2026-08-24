<?php

use App\Support\SettingStore;
use Illuminate\Support\Facades\Route;

test('missing client routes render the branded responsive 404 page', function (): void {
    $this->get('/duong-dan-khong-ton-tai-'.uniqid())
        ->assertNotFound()
        ->assertSee('data-error-page="404"', false)
        ->assertSee('Không tìm thấy trang bạn đang truy cập')
        ->assertSee('meta name="robots" content="noindex,nofollow"', false)
        ->assertSee('resources/css/client.css', false)
        ->assertDontSee('resources/css/app.css', false);
});

test('error page still renders when database backed settings are unavailable', function (): void {
    $originalSettingStore = $this->app->make(SettingStore::class);
    $settingStore = Mockery::mock(SettingStore::class);
    $settingStore->shouldReceive('getMany')->once()->andThrow(new RuntimeException('Database unavailable'));
    $this->app->instance(SettingStore::class, $settingStore);

    try {
        $this->get('/duong-dan-loi-cau-hinh-'.uniqid())
            ->assertNotFound()
            ->assertSee('data-error-page="404"', false)
            ->assertSee(config('app.name'));
    } finally {
        $this->app->instance(SettingStore::class, $originalSettingStore);
    }
});

test('authorization and server failures share the same error page contract', function (int $statusCode, string $headline): void {
    $path = '/__kiem-tra-error-'.$statusCode;
    Route::get($path, fn () => abort($statusCode));

    $this->get($path)
        ->assertStatus($statusCode)
        ->assertSee('data-error-page="'.$statusCode.'"', false)
        ->assertSee($headline);
})->with([
    'forbidden' => [403, 'Bạn không có quyền truy cập trang này'],
    'server error' => [500, 'Hệ thống chưa thể hoàn tất yêu cầu'],
    'maintenance' => [503, 'Hệ thống đang tạm bảo trì'],
]);
