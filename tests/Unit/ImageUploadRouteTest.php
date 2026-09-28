<?php

use App\Features\Admin\Upload\Services\ImageUploadService;
use App\Features\Admin\Upload\Services\RemoteImageUploadService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('image upload route is registered for authenticated admins', function (): void {
    /** @var Route|null $route */
    $route = app('router')->getRoutes()->getByName('admin.uploads.image.store');

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe('api/uploads/image')
        ->and($route?->methods())->toContain('POST')
        ->and($route?->gatherMiddleware())->toContain('auth:sanctum', 'admin', 'throttle:30,1');

    $this->postJson('/api/uploads/image')->assertUnauthorized();

    /** @var Route|null $importRoute */
    $importRoute = app('router')->getRoutes()->getByName('admin.uploads.image.import');

    expect($importRoute)->not->toBeNull()
        ->and($importRoute?->uri())->toBe('api/uploads/image/import')
        ->and($importRoute?->methods())->toContain('POST')
        ->and($importRoute?->gatherMiddleware())->toContain('auth:sanctum', 'admin', 'throttle:30,1');

    $this->postJson('/api/uploads/image/import', [
        'url' => 'https://cdn.example.com/photo.webp',
    ])->assertUnauthorized();
});

test('remote image import blocks internal network urls without making a request', function (): void {
    Http::preventStrayRequests();

    $admin = new User;
    $admin->forceFill(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson('/api/uploads/image/import', ['url' => 'http://127.0.0.1/private.png'])
        ->assertUnprocessable()
        ->assertJsonPath('status', false);

    Http::assertNothingSent();
});

test('remote image import stores a public image with a relative napcarot url', function (): void {
    Storage::fake('public');
    Http::preventStrayRequests();

    $webpBinary = base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEALmk0mk0iIiIiIgBoSygABc6zbAAA', true);
    Http::fake([
        'https://cdn.example.com/photo.webp' => Http::response($webpBinary, 200, ['Content-Type' => 'image/webp']),
    ]);

    $imageUploadService = new class extends ImageUploadService
    {
        protected function supportsServerSideWebpConversion(): bool
        {
            return false;
        }

        protected function publicUploadRelativePath(string $filename): string
        {
            return 'uploads/testing/image/'.$filename;
        }
    };
    app()->instance(RemoteImageUploadService::class, new class($imageUploadService) extends RemoteImageUploadService
    {
        protected function resolveHostAddresses(string $host): array
        {
            return ['93.184.216.34'];
        }
    });

    $admin = new User;
    $admin->forceFill(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson('/api/uploads/image/import', [
        'url' => 'https://cdn.example.com/photo.webp',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.extension', 'webp');

    expect($response->json('data.url'))
        ->toStartWith('/storage/uploads/testing/image/')
        ->not->toContain('://');
    Storage::disk('public')->assertExists($response->json('data.path'));
    Http::assertSentCount(1);
});

test('image upload route rejects non-admin users and invalid files', function (): void {
    $regularUser = new User;
    $regularUser->forceFill(['role' => 'user']);

    $this->actingAs($regularUser)
        ->postJson('/api/uploads/image', [
            'image' => UploadedFile::fake()->create('payload.txt', 1, 'text/plain'),
        ])
        ->assertForbidden();

    $admin = new User;
    $admin->forceFill(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson('/api/uploads/image', [
            'image' => UploadedFile::fake()->create('payload.txt', 1, 'text/plain'),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('status', false);
});

test('image upload route accepts a multipart webp image from an admin', function (): void {
    Storage::fake('public');

    $temporaryWebpPath = tempnam(sys_get_temp_dir(), 'editor-webp-');
    file_put_contents(
        $temporaryWebpPath,
        base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEALmk0mk0iIiIiIgBoSygABc6zbAAA', true),
    );

    $admin = new User;
    $admin->forceFill(['role' => 'admin']);

    try {
        $response = $this->actingAs($admin)->post('/api/uploads/image', [
            'image' => new UploadedFile($temporaryWebpPath, 'editor.webp', 'image/webp', null, true),
            'name' => 'Ảnh bài viết',
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.extension', 'webp')
            ->assertJsonPath('data.mime_type', 'image/webp');

        Storage::disk('public')->assertExists($response->json('data.path'));
    } finally {
        @unlink($temporaryWebpPath);
    }
});
