<?php

use App\Features\Admin\Upload\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('image upload service stores an existing webp on the public disk', function (): void {
    Storage::fake('public');

    $temporaryWebpPath = tempnam(sys_get_temp_dir(), 'webp-test-');
    file_put_contents($temporaryWebpPath, 'RIFFxxxxWEBPVP8 ');

    $uploadedFile = new UploadedFile(
        $temporaryWebpPath,
        'logo.webp',
        'image/webp',
        null,
        true,
    );

    $service = new class extends ImageUploadService
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

    try {
        $uploaded = $service->store($uploadedFile, 'Logo WebP');

        expect($uploaded['extension'])->toBe('webp')
            ->and($uploaded['mime_type'])->toBe('image/webp')
            ->and($uploaded['path'])->toStartWith('uploads/testing/image/logo-webp-')
            ->and($uploaded['url'])->toContain('/storage/uploads/testing/image/');

        Storage::disk('public')->assertExists($uploaded['path']);
    } finally {
        @unlink($temporaryWebpPath);
    }
});

test('image upload service preserves a png when gd webp conversion is unavailable', function (): void {
    Storage::fake('public');

    $temporaryPngPath = tempnam(sys_get_temp_dir(), 'png-test-');
    file_put_contents(
        $temporaryPngPath,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
    );

    $uploadedFile = new UploadedFile($temporaryPngPath, 'photo.png', 'image/png', null, true);
    $service = new class extends ImageUploadService
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

    try {
        $uploaded = $service->store($uploadedFile, 'Ảnh PNG');

        expect($uploaded['extension'])->toBe('png')
            ->and($uploaded['mime_type'])->toBe('image/png')
            ->and($uploaded['url'])->toStartWith('/storage/uploads/testing/image/')
            ->not->toContain('://');

        Storage::disk('public')->assertExists($uploaded['path']);
    } finally {
        @unlink($temporaryPngPath);
    }
});
