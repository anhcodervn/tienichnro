<?php

use App\Features\Admin\Topup\Services\GameImageService;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function validPngUpload(string $name = 'game.png'): UploadedFile
{
    $content = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true,
    );

    return UploadedFile::fake()->createWithContent($name, $content);
}

function emptySquareWebpUpload(): UploadedFile
{
    $canvas = chr(0)."\0\0\0".substr(pack('V', 511), 0, 3).substr(pack('V', 511), 0, 3);
    $content = 'RIFF'.pack('V', 22).'WEBPVP8X'.pack('V', 10).$canvas;

    return UploadedFile::fake()->createWithContent('game.webp', $content);
}

test('game image service stores an exact 512 webp when conversion is unavailable', function (): void {
    Storage::fake('public');
    $service = new class extends GameImageService
    {
        protected function supportsServerSideWebpConversion(): bool
        {
            return false;
        }
    };

    $result = $service->store(emptySquareWebpUpload(), 'Ngọc Rồng Online');

    expect($result)
        ->width->toBe(512)
        ->height->toBe(512)
        ->mime_type->toBe('image/webp')
        ->extension->toBe('webp');
    Storage::disk('public')->assertExists($result['path']);
});

test('game image service rejects a webp with the wrong size when conversion is unavailable', function (): void {
    Storage::fake('public');
    $service = new class extends GameImageService
    {
        protected function supportsServerSideWebpConversion(): bool
        {
            return false;
        }
    };

    $canvas = chr(0)."\0\0\0".substr(pack('V', 255), 0, 3).substr(pack('V', 255), 0, 3);
    $content = 'RIFF'.pack('V', 22).'WEBPVP8X'.pack('V', 10).$canvas;
    $upload = UploadedFile::fake()->createWithContent('game.webp', $content);

    expect(fn () => $service->store($upload, 'wrong-size'))
        ->toThrow(RuntimeException::class, 'WebP đúng 512x512');
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('game image upload is protected by admin middleware', function (): void {
    $this->post('/api/admin-api/games/image', [
        'image' => validPngUpload(),
    ], ['Accept' => 'application/json'])->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->post('/api/admin-api/games/image', [
            'image' => validPngUpload(),
        ], ['Accept' => 'application/json'])
        ->assertForbidden();
});

test('admin can upload a game image through the dedicated endpoint', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $uploadedImage = [
        'path' => 'uploads/games/2026/09/22/ngoc-rong-online.webp',
        'url' => '/storage/uploads/games/2026/09/22/ngoc-rong-online.webp',
        'mime_type' => 'image/webp',
        'extension' => 'webp',
        'size' => 1024,
        'width' => 512,
        'height' => 512,
    ];
    $service = Mockery::mock(GameImageService::class);
    $service->shouldReceive('store')
        ->once()
        ->with(Mockery::type(UploadedFile::class), 'ngoc-rong-online')
        ->andReturn($uploadedImage);
    app()->instance(GameImageService::class, $service);

    $this->actingAs($admin)
        ->post('/api/admin-api/games/image', [
            'image' => validPngUpload(),
            'name' => 'ngoc-rong-online',
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.url', $uploadedImage['url'])
        ->assertJsonPath('data.mime_type', 'image/webp')
        ->assertJsonPath('data.width', 512)
        ->assertJsonPath('data.height', 512);
});

test('game image upload rejects unsupported files', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post('/api/admin-api/games/image', [
            'image' => UploadedFile::fake()->createWithContent('game.txt', 'not an image'),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('status', false)
        ->assertJsonStructure(['errors' => ['image']]);
});

test('admin can save the uploaded image url on a game', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $imageUrl = '/storage/uploads/games/2026/09/22/ngoc-rong-online.webp';

    $response = $this->actingAs($admin)->postJson('/api/admin-api/games', [
        'name' => 'Ngọc Rồng Online',
        'slug' => 'ngoc-rong-online-image-test',
        'short_name' => 'NRO',
        'reward_label' => 'Ngọc',
        'provider_service_code' => 'nro',
        'package_mode' => 'custom',
        'image' => $imageUrl,
        'status' => 'active',
        'sort_order' => 0,
        'checkout_fields' => [[
            'key' => 'game_account',
            'label' => 'Tài khoản game',
            'placeholder' => '',
            'required' => true,
            'regex' => '',
        ]],
    ]);

    $response->assertCreated()->assertJsonPath('data.image', $imageUrl);

    expect(Game::query()->where('slug', 'ngoc-rong-online-image-test')->value('image'))->toBe($imageUrl);
});
