<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
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
