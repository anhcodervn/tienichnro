<?php

namespace App\Features\Admin\Upload\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class Base64ImageUploadService
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** Ten MiB expands to 13,981,016 base64 bytes, plus a bounded Data URI header. */
    public const MAX_ENCODED_LENGTH = 13981016 + 128;

    public function __construct(private readonly ImageUploadService $imageUploadService) {}

    /** @return array{path: string, url: string, mime_type: string, extension: string, size: int} */
    public function store(string $encoded, ?string $name = null): array
    {
        if (strlen($encoded) > self::MAX_ENCODED_LENGTH) {
            throw new RuntimeException('Ảnh base64 không được vượt quá 10 MB sau giải mã.');
        }

        $declaredMime = null;
        if (strncasecmp($encoded, 'data:', 5) === 0) {
            if (! preg_match('/\Adata:(image\/(?:jpeg|jpg|png|webp));base64,/i', $encoded, $matches)) {
                throw new RuntimeException('Data URI phải chứa ảnh JPG, PNG hoặc WebP dạng base64.');
            }
            $declaredMime = strtolower($matches[1]);
            $declaredMime = $declaredMime === 'image/jpg' ? 'image/jpeg' : $declaredMime;
            $encoded = substr($encoded, strlen($matches[0]));
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('Dữ liệu base64 của ảnh không hợp lệ.');
        }
        if (strlen($binary) > self::MAX_BYTES) {
            throw new RuntimeException('Ảnh base64 không được vượt quá 10 MB sau giải mã.');
        }

        $info = @getimagesizefromstring($binary);
        $mime = is_array($info) ? ($info['mime'] ?? null) : null;
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Base64 phải chứa nội dung ảnh JPG, PNG hoặc WebP hợp lệ.'),
        };
        if ($declaredMime !== null && $declaredMime !== $mime) {
            throw new RuntimeException('Định dạng Data URI không khớp nội dung ảnh.');
        }
        if ($info[0] > 8000 || $info[1] > 8000) {
            throw new RuntimeException('Kích thước ảnh không được vượt quá 8000 x 8000 pixel.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'base64-image-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Không thể khởi tạo tệp tạm để xử lý ảnh.');
        }
        try {
            if (file_put_contents($temporaryPath, $binary) !== strlen($binary)) {
                throw new RuntimeException('Không thể ghi nội dung ảnh tạm.');
            }
            $file = new UploadedFile($temporaryPath, 'base64-image.'.$extension, $mime, null, true);

            return $this->imageUploadService->store($file, $name);
        } finally {
            @unlink($temporaryPath);
        }
    }
}
