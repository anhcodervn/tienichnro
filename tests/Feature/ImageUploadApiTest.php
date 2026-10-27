<?php

use App\Features\Admin\Upload\Requests\StoreImageUploadRequest;
use App\Features\Admin\Upload\Services\Base64ImageUploadService;
use App\Features\Admin\Upload\Services\ImageUploadService;
use App\Features\Admin\Upload\Services\RemoteImageUploadService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

function imageApiBinary(string $format = 'png'): string
{
    return base64_decode(match ($format) {
        'png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        'webp' => 'UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoIAAgAAUAmJaQAA3AA/v02aAA=',
        'jpeg' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwC7RRRXunin/9k=',
    }, true);
}

function imageApiRemote(): void
{
    app()->instance(RemoteImageUploadService::class, new class(app(ImageUploadService::class)) extends RemoteImageUploadService
    {
        protected function resolveHostAddresses(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
}

beforeEach(function (): void {
    Storage::fake('public');
    Http::preventStrayRequests();
    $this->travelTo(now()->setDate(2026, 10, 18)->setTime(12, 0));
});

afterEach(function (): void {
    $this->travelBack();
});

test('admin uploads raw base64 or data uri images into a daily directory', function (string $format, bool $dataUri): void {
    $encoded = base64_encode(imageApiBinary($format));
    if ($dataUri) {
        $encoded = 'data:image/'.$format.';base64,'.$encoded;
    }
    $admin = User::factory()->create(['role' => 'admin']);
    $response = $this->withToken($admin->createToken('image-upload-test')->plainTextToken)
        ->postJson('/api/uploads/image', ['base64' => $encoded, 'name' => 'item-icon']);

    $response->assertOk()->assertJsonPath('status', true);
    expect($response->json('data.path'))->toStartWith('uploads/2026-10-18/image/item-icon-')
        ->and($response->json('data.url'))->toBe('/storage/'.$response->json('data.path'))
        ->and($response->json('data.size'))->toBeGreaterThan(0);
    Storage::disk('public')->assertExists($response->json('data.path'));
})->with([['png', false], ['png', true], ['jpeg', false], ['jpeg', true], ['webp', false], ['webp', true]]);

test('multipart files use the same daily upload directory and collision safe names', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $paths = [];
    foreach (range(1, 2) as $number) {
        $response = $this->post('/api/uploads/image', [
            'image' => UploadedFile::fake()->createWithContent('image.png', imageApiBinary()),
            'name' => '../../same-name',
        ], ['Accept' => 'application/json'])->assertOk();
        $paths[] = $response->json('data.path');
        expect($response->json('data.path'))->toStartWith('uploads/2026-10-18/image/same-name-')->not->toContain('..');
        Storage::disk('public')->assertExists($response->json('data.path'));
    }
    expect(array_unique($paths))->toHaveCount(2);
});

test('unified url uploads reuse the protected downloader and supplied name', function (): void {
    imageApiRemote();
    Http::fake(['https://cdn.example.com/photo.png' => Http::response(imageApiBinary(), 200)]);
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->postJson('/api/uploads/image', ['url' => 'https://cdn.example.com/photo.png', 'name' => 'remote-icon'])
        ->assertOk()->assertJsonPath('status', true);

    expect($response->json('data.path'))->toStartWith('uploads/2026-10-18/image/remote-icon-');
    Storage::disk('public')->assertExists($response->json('data.path'));
    Http::assertSentCount(1);
});

test('upload date changes at midnight without moving older images', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $payload = ['base64' => base64_encode(imageApiBinary())];
    $first = $this->postJson('/api/uploads/image', $payload)->assertOk()->json('data.path');
    $this->travel(1)->days();
    $second = $this->postJson('/api/uploads/image', $payload)->assertOk()->json('data.path');

    expect($first)->toStartWith('uploads/2026-10-18/image/')
        ->and($second)->toStartWith('uploads/2026-10-19/image/');
    Storage::disk('public')->assertExists([$first, $second]);
});

test('image upload requires an authenticated admin for base64 and url', function (): void {
    $payload = ['base64' => base64_encode(imageApiBinary())];
    $this->postJson('/api/uploads/image', $payload)->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'user']))
        ->postJson('/api/uploads/image', $payload)->assertForbidden();
    $this->postJson('/api/uploads/image', ['url' => 'https://cdn.example.com/photo.png'])->assertForbidden();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
    Http::assertNothingSent();
});

test('invalid ambiguous or missing image sources are rejected without writing files', function (array $payload): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->postJson('/api/uploads/image', $payload)->assertUnprocessable()->assertJsonPath('status', false);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
    Http::assertNothingSent();
})->with([
    'missing source' => [[]],
    'empty base64' => [['base64' => '']],
    'invalid encoding' => [['base64' => 'not@@base64']],
    'base64 text' => [['base64' => base64_encode('not an image')]],
    'script data uri' => [['base64' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=']],
    'multiple sources' => [['base64' => 'YWJj', 'url' => 'https://cdn.example.com/photo.png']],
    'invalid url scheme' => [['url' => 'file:///etc/passwd']],
    'internal url' => [['url' => 'http://127.0.0.1/private.png']],
]);

test('base64 mime mismatch oversize and excessive dimensions are rejected', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $mismatch = 'data:image/jpeg;base64,'.base64_encode(imageApiBinary());
    $large = base64_encode(str_repeat('x', 10 * 1024 * 1024 + 1));
    $oversizedHeader = 'RIFF'.pack('V', 22).'WEBPVP8X'.pack('V', 10).chr(0)."\0\0\0".substr(pack('V', 8000), 0, 3).substr(pack('V', 8000), 0, 3);
    foreach ([$mismatch, $large, base64_encode($oversizedHeader)] as $encoded) {
        expect(fn () => app(Base64ImageUploadService::class)->store($encoded))->toThrow(RuntimeException::class);
    }
    $this->postJson('/api/uploads/image', ['base64' => $mismatch])->assertUnprocessable();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('url imports reject redirects into private networks', function (): void {
    imageApiRemote();
    Http::fake(['https://cdn.example.com/photo.png' => Http::response('', 302, ['Location' => 'http://127.0.0.1/private.png'])]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->postJson('/api/uploads/image', ['url' => 'https://cdn.example.com/photo.png'])->assertUnprocessable();
    Http::assertSentCount(1);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('multipart and base64 cannot be combined in a single upload', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post('/api/uploads/image', [
            'image' => UploadedFile::fake()->createWithContent('image.png', imageApiBinary()),
            'base64' => base64_encode(imageApiBinary()),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('encoded inputs above the size cap are rejected before decoding', function (): void {
    $encoded = str_repeat('A', Base64ImageUploadService::MAX_ENCODED_LENGTH + 1);
    expect(fn () => app(Base64ImageUploadService::class)->store($encoded))
        ->toThrow(RuntimeException::class, '10 MB');
    $request = new StoreImageUploadRequest;
    $validator = Validator::make(['base64' => $encoded], $request->rules());
    expect($validator->fails())->toBeTrue()->and($validator->errors()->has('base64'))->toBeTrue();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('base64 temporary files are cleaned up after storage failure', function (): void {
    $images = new class extends ImageUploadService
    {
        public ?string $temporaryPath = null;

        public function store(UploadedFile $file, ?string $name = null, ?array $gameAsset = null): array
        {
            $this->temporaryPath = $file->getRealPath();
            throw new RuntimeException('Storage unavailable');
        }
    };
    $service = new Base64ImageUploadService($images);
    expect(fn () => $service->store(base64_encode(imageApiBinary())))
        ->toThrow(RuntimeException::class, 'Storage unavailable');
    expect($images->temporaryPath)->not->toBeNull()
        ->and(file_exists($images->temporaryPath))->toBeFalse();
});

test('unreachable urls and non image responses do not create uploads', function (int $status, string $body): void {
    imageApiRemote();
    Http::fake(['https://cdn.example.com/photo.png' => Http::response($body, $status)]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->postJson('/api/uploads/image', ['url' => 'https://cdn.example.com/photo.png'])->assertUnprocessable();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
})->with([[500, 'Unavailable'], [200, '<html>not an image</html>']]);
