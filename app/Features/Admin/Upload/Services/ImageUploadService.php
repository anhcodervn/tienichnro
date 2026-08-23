<?php

namespace App\Features\Admin\Upload\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageUploadService
{
    private const MAX_DIMENSION = 1800;

    private const WEBP_QUALITY = 82;

    /**
     * @return array{path: string, url: string, mime_type: string, extension: string, size: int}
     */
    public function store(UploadedFile $file, ?string $name = null): array
    {
        if (! $this->supportsServerSideWebpConversion()) {
            if ($this->isWebpUpload($file)) {
                return $this->storeExistingWebp($file, $name);
            }

            throw new RuntimeException('Máy chủ chưa cài GD để xử lý ảnh WebP. Vui lòng bật GD hoặc gửi ảnh WebP từ trình duyệt.');
        }

        $imageResource = $this->createImageResource($file);

        [$targetWidth, $targetHeight] = $this->calculateDimensions(
            imagesx($imageResource),
            imagesy($imageResource),
        );

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            imagedestroy($imageResource);

            throw new RuntimeException('Không thể khởi tạo bộ nhớ xử lý ảnh.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        imagecopyresampled(
            $canvas,
            $imageResource,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            imagesx($imageResource),
            imagesy($imageResource),
        );

        ob_start();
        imagewebp($canvas, null, self::WEBP_QUALITY);
        $binary = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($imageResource);

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Không thể chuyển đổi ảnh sang WebP.');
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

    protected function publicUploadRelativePath(string $filename): string
    {
        return 'uploads/image/'.now()->format('Y/m/d').'/'.$filename;
    }

    private function isWebpUpload(UploadedFile $file): bool
    {
        return Str::lower((string) $file->getClientOriginalExtension()) === 'webp'
            || Str::lower((string) $file->getMimeType()) === 'image/webp';
    }

    /**
     * @return array{path: string, url: string, mime_type: string, extension: string, size: int}
     */
    private function storeExistingWebp(UploadedFile $file, ?string $name = null): array
    {
        $binary = file_get_contents($file->getRealPath());

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Không thể đọc nội dung ảnh tải lên.');
        }

        return $this->persistBinary($binary, $file, $name);
    }

    /**
     * @return array{path: string, url: string, mime_type: string, extension: string, size: int}
     */
    private function persistBinary(string $binary, UploadedFile $file, ?string $name = null): array
    {
        $filename = $this->buildFilename($file, $name);
        $path = $this->publicUploadRelativePath($filename);
        $stored = Storage::disk('public')->put($path, $binary, [
            'visibility' => 'public',
            'mimetype' => 'image/webp',
        ]);

        if (! $stored) {
            throw new RuntimeException('Không thể lưu ảnh tải lên.');
        }

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'size' => strlen($binary),
        ];
    }

    private function createImageResource(UploadedFile $file): mixed
    {
        $imageContent = file_get_contents($file->getRealPath());

        if (! is_string($imageContent) || $imageContent === '') {
            throw new RuntimeException('Không thể đọc nội dung ảnh tải lên.');
        }

        $resource = imagecreatefromstring($imageContent);

        if ($resource === false) {
            throw new RuntimeException('Định dạng ảnh không được hỗ trợ.');
        }

        return $resource;
    }

    /** @return array{0: int, 1: int} */
    private function calculateDimensions(int $width, int $height): array
    {
        if ($width <= self::MAX_DIMENSION && $height <= self::MAX_DIMENSION) {
            return [$width, $height];
        }

        $ratio = min(self::MAX_DIMENSION / $width, self::MAX_DIMENSION / $height);

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    private function buildFilename(UploadedFile $file, ?string $name = null): string
    {
        $baseName = $name !== null && trim($name) !== ''
            ? trim($name)
            : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $slug = Str::slug($baseName);

        if ($slug === '') {
            $slug = 'image';
        }

        return $slug.'-'.Str::lower((string) Str::uuid()).'.webp';
    }
}
