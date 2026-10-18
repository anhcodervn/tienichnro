<?php

use App\Features\Admin\Upload\Services\ImageUploadService;
use App\Features\Admin\Upload\Services\ImageWatermarkService;
use App\Features\Admin\Upload\Services\RemoteImageUploadService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function watermarkPng(int $width = 600, int $height = 400): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 100, 140, 180));
    ob_start();
    imagepng($image);
    $binary = ob_get_clean();
    imagedestroy($image);

    return $binary;
}

beforeEach(function (): void {
    Storage::fake('public');
    Http::preventStrayRequests();
});

test('watermark is a faint repeated grid across all image quadrants', function (): void {
    $image = imagecreatetruecolor(600, 400);
    imagefill($image, 0, 0, imagecolorallocate($image, 100, 140, 180));
    app(ImageWatermarkService::class)->apply($image);
    $quadrants = [0, 0, 0, 0];
    $totalDifference = 0;
    $peakDifference = 0;
    for ($y = 0; $y < 400; $y++) {
        for ($x = 0; $x < 600; $x++) {
            $color = imagecolorat($image, $x, $y);
            $difference = max(abs((($color >> 16) & 255) - 100), abs((($color >> 8) & 255) - 140), abs(($color & 255) - 180));
            if ($difference > 0) {
                $quadrants[($y >= 200 ? 2 : 0) + ($x >= 300 ? 1 : 0)]++;
            }
            $totalDifference += $difference;
            $peakDifference = max($peakDifference, $difference);
        }
    }
    imagedestroy($image);
    foreach ($quadrants as $changed) {
        expect($changed)->toBeGreaterThan(100);
    }
    expect($totalDifference / (600 * 400))->toBeLessThan(8)
        ->and($peakDifference)->toBeLessThan(45);
});

test('watermark preserves a transparent background outside its text', function (): void {
    $image = imagecreatetruecolor(256, 256);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    app(ImageWatermarkService::class)->apply($image);
    $transparent = 0;
    $faint = 0;
    for ($y = 0; $y < 256; $y++) {
        for ($x = 0; $x < 256; $x++) {
            $alpha = (imagecolorat($image, $x, $y) >> 24) & 127;
            $transparent += $alpha === 127 ? 1 : 0;
            $faint += $alpha > 90 && $alpha < 127 ? 1 : 0;
        }
    }
    imagedestroy($image);
    expect($transparent)->toBeGreaterThan(256 * 256 * 0.7)->and($faint)->toBeGreaterThan(100);
});

test('every upload input returns an actually watermarked image', function (string $source): void {
    $binary = watermarkPng();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    if ($source === 'url' || $source === 'import') {
        Http::fake(['https://cdn.example.com/image.png' => Http::response($binary, 200)]);
        app()->instance(RemoteImageUploadService::class, new class(app(ImageUploadService::class)) extends RemoteImageUploadService
        {
            protected function resolveHostAddresses(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
    }
    $payload = match ($source) {
        'base64' => ['base64' => base64_encode($binary)],
        'url', 'import' => ['url' => 'https://cdn.example.com/image.png'],
        'file' => ['image' => UploadedFile::fake()->createWithContent('image.png', $binary)],
    };
    $endpoint = $source === 'import' ? '/api/uploads/image/import' : '/api/uploads/image';
    $response = $this->post($endpoint, $payload, ['Accept' => 'application/json'])->assertOk();
    $stored = Storage::disk('public')->get($response->json('data.path'));
    $decoded = imagecreatefromstring($stored);
    $changed = 0;
    for ($y = 0; $y < 400; $y += 2) {
        for ($x = 0; $x < 600; $x += 2) {
            $color = imagecolorat($decoded, $x, $y);
            if (abs((($color >> 16) & 255) - 100) > 8 || abs((($color >> 8) & 255) - 140) > 8) {
                $changed++;
            }
        }
    }
    expect(imagesx($decoded))->toBe(600)->and(imagesy($decoded))->toBe(400)->and($changed)->toBeGreaterThan(100);
    imagedestroy($decoded);
})->with(['base64', 'url', 'file', 'import']);

test('resizing and tiny image dimensions remain valid after watermarking', function (int $width, int $height, int $expectedWidth, int $expectedHeight): void {
    $upload = UploadedFile::fake()->createWithContent('image.png', watermarkPng($width, $height));
    $result = app(ImageUploadService::class)->store($upload);
    $info = getimagesizefromstring(Storage::disk('public')->get($result['path']));
    expect($info[0])->toBe($expectedWidth)->and($info[1])->toBe($expectedHeight);
})->with([[3600, 1200, 1800, 600], [16, 16, 16, 16], [1, 1, 1, 1]]);

test('missing GD returns an explicit unavailable error and stores no unmarked copy', function (string $source): void {
    app()->instance(ImageUploadService::class, new class extends ImageUploadService
    {
        protected function supportsImageProcessing(): bool
        {
            return false;
        }
    });
    $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    if ($source === 'url' || $source === 'import') {
        Http::fake(['https://cdn.example.com/image.png' => Http::response(base64_decode($base64, true), 200)]);
        app()->instance(RemoteImageUploadService::class, new class(app(ImageUploadService::class)) extends RemoteImageUploadService
        {
            protected function resolveHostAddresses(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
    }
    $payload = match ($source) {
        'base64' => ['base64' => $base64],
        'url', 'import' => ['url' => 'https://cdn.example.com/image.png'],
        'file' => ['image' => UploadedFile::fake()->createWithContent('image.png', base64_decode($base64, true))],
    };
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post($source === 'import' ? '/api/uploads/image/import' : '/api/uploads/image', $payload, ['Accept' => 'application/json'])
        ->assertStatus(503)->assertJsonPath('status', false)->assertJsonFragment(['message' => 'Máy chủ cần bật PHP GD để gắn watermark tienichnro.com vào ảnh.']);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
})->with(['base64', 'url', 'file', 'import']);

test('produces a visual preview from the actual watermark upload pipeline', function (): void {
    $image = imagecreatetruecolor(960, 600);
    imagefill($image, 0, 0, imagecolorallocate($image, 235, 240, 244));
    imagefilledrectangle($image, 0, 0, 959, 270, imagecolorallocate($image, 45, 86, 112));
    imagefilledellipse($image, 760, 140, 140, 140, imagecolorallocate($image, 240, 202, 126));
    imagefilledpolygon($image, [0, 270, 240, 100, 470, 270], imagecolorallocate($image, 85, 128, 145));
    imagefilledpolygon($image, [260, 270, 510, 125, 800, 270], imagecolorallocate($image, 115, 159, 158));
    imagestring($image, 5, 70, 340, 'IMAGE PREVIEW', imagecolorallocate($image, 35, 65, 80));
    imagestring($image, 4, 70, 380, 'Soft grid watermark - tienichnro.com', imagecolorallocate($image, 65, 80, 95));
    imagefilledrectangle($image, 70, 430, 330, 500, imagecolorallocate($image, 95, 145, 155));
    imagefilledrectangle($image, 350, 430, 610, 500, imagecolorallocate($image, 210, 166, 113));
    imagefilledrectangle($image, 630, 430, 890, 500, imagecolorallocate($image, 132, 143, 173));
    ob_start();
    imagepng($image);
    $binary = ob_get_clean();
    imagedestroy($image);
    $result = app(ImageUploadService::class)->store(UploadedFile::fake()->createWithContent('preview.png', $binary));
    file_put_contents(storage_path('framework/testing/watermark-preview.webp'), Storage::disk('public')->get($result['path']));
    expect($result['mime_type'])->toBe('image/webp');
});
