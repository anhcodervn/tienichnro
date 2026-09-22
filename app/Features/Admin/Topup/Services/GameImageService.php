<?php

namespace App\Features\Admin\Topup\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GameImageService
{
    private const IMAGE_SIZE = 512;

    private const WEBP_QUALITY = 82;

    /**
     * @return array{path: string, url: string, mime_type: string, extension: string, size: int, width: int, height: int}
     */
    public function store(UploadedFile $file, ?string $name = null): array
    {
        $imageInfo = @getimagesize($file->getRealPath());

        if ($imageInfo === false) {
            throw new RuntimeException('Không thể đọc kích thước ảnh tải lên.');
        }

        if (! $this->supportsServerSideWebpConversion()) {
            return $this->storeExistingSquareWebp($file, $imageInfo, $name);
        }

        $source = $this->createImageResource($file);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $cropSize = min($sourceWidth, $sourceHeight);
        $sourceX = (int) floor(($sourceWidth - $cropSize) / 2);
        $sourceY = (int) floor(($sourceHeight - $cropSize) / 2);
        $canvas = imagecreatetruecolor(self::IMAGE_SIZE, self::IMAGE_SIZE);

        if ($canvas === false) {
            imagedestroy($source);

            throw new RuntimeException('Không thể khởi tạo bộ nhớ xử lý ảnh.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            self::IMAGE_SIZE,
            self::IMAGE_SIZE,
            $cropSize,
            $cropSize,
        );

        ob_start();
        $converted = imagewebp($canvas, null, self::WEBP_QUALITY);
        $binary = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        if (! $converted || ! is_string($binary) || $binary === '') {
            throw new RuntimeException('Không thể chuyển đổi ảnh game sang WebP.');
        }

        return $this->persistBinary($binary, $file, $name);
    }

    protected function supportsServerSideWebpConversion(): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagewebp')
            && defined('IMG_WEBP')
            && (imagetypes() & IMG_WEBP) === IMG_WEBP;
    }

    /**
     * @param  array<int|string, mixed>  $imageInfo
     * @return array{path: string, url: string, mime_type: string, extension: string, size: int, width: int, height: int}
     */
    private function storeExistingSquareWebp(UploadedFile $file, array $imageInfo, ?string $name): array
    {
        if (($imageInfo['mime'] ?? null) !== 'image/webp'
            || ($imageInfo[0] ?? null) !== self::IMAGE_SIZE
            || ($imageInfo[1] ?? null) !== self::IMAGE_SIZE) {
            throw new RuntimeException('Máy chủ chưa hỗ trợ chuyển đổi WebP. Vui lòng bật GD/WebP hoặc tải ảnh WebP đúng 512x512.');
        }

        $binary = file_get_contents($file->getRealPath());

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Không thể đọc nội dung ảnh tải lên.');
        }

        return $this->persistBinary($binary, $file, $name);
    }

    /**
     * @return array{path: string, url: string, mime_type: string, extension: string, size: int, width: int, height: int}
     */
    private function persistBinary(string $binary, UploadedFile $file, ?string $name): array
    {
        $baseName = $name !== null && trim($name) !== ''
            ? trim($name)
            : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = Str::slug($baseName) ?: 'game';
        $filename = $slug.'-'.Str::lower((string) Str::uuid()).'.webp';
        $path = 'uploads/games/'.now()->format('Y/m/d').'/'.$filename;
        $stored = Storage::disk('public')->put($path, $binary, [
            'visibility' => 'public',
            'mimetype' => 'image/webp',
        ]);

        if (! $stored) {
            throw new RuntimeException('Không thể lưu ảnh game lên máy chủ.');
        }

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'size' => strlen($binary),
            'width' => self::IMAGE_SIZE,
            'height' => self::IMAGE_SIZE,
        ];
    }

    private function createImageResource(UploadedFile $file): mixed
    {
        $binary = file_get_contents($file->getRealPath());

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Không thể đọc nội dung ảnh tải lên.');
        }

        $resource = @imagecreatefromstring($binary);

        if ($resource === false) {
            throw new RuntimeException('Định dạng ảnh không được hỗ trợ.');
        }

        return $resource;
    }
}
