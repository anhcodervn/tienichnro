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

    public function __construct(private readonly ImageWatermarkService $watermark = new ImageWatermarkService) {}

    /** @return array{path: string, url: string, mime_type: string, extension: string, size: int} */
    public function store(UploadedFile $file, ?string $name = null): array
    {
        if (! $this->supportsImageProcessing()) {
            throw new RuntimeException('Máy chủ cần bật PHP GD để gắn watermark tienichnro.com vào ảnh.', 503);
        }
        $imageResource = $this->createImageResource($file);
        $canvas = null;
        try {
            [$targetWidth, $targetHeight] = $this->calculateDimensions(imagesx($imageResource), imagesy($imageResource));
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($canvas === false) {
                throw new RuntimeException('Không thể khởi tạo bộ nhớ xử lý ảnh.');
            }
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            imagecopyresampled($canvas, $imageResource, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($imageResource), imagesy($imageResource));
            $this->watermark->apply($canvas);
            $mimeType = $this->supportsServerSideWebpConversion() ? 'image/webp' : $file->getMimeType();
            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => throw new RuntimeException('Định dạng ảnh không được hỗ trợ.'),
            };
            ob_start();
            try {
                $encoded = match ($mimeType) {
                    'image/jpeg' => imagejpeg($canvas, null, self::WEBP_QUALITY),
                    'image/png' => imagepng($canvas),
                    'image/webp' => imagewebp($canvas, null, self::WEBP_QUALITY),
                };
                $binary = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            if (! $encoded || ! is_string($binary) || $binary === '') {
                throw new RuntimeException('Không thể mã hóa ảnh sau khi gắn watermark.');
            }

            return $this->persistBinary($binary, $file, $name, $mimeType, $extension);
        } finally {
            imagedestroy($imageResource);
            if ($canvas instanceof \GdImage) {
                imagedestroy($canvas);
            }
        }
    }

    protected function supportsImageProcessing(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor') && function_exists('imagestring');
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
        return 'uploads/'.now()->format('Y-m-d').'/image/'.$filename;
    }

    /**
     * @return array{path: string, url: string, mime_type: string, extension: string, size: int}
     */
    private function persistBinary(
        string $binary,
        UploadedFile $file,
        ?string $name,
        string $mimeType,
        string $extension,
    ): array {
        $filename = $this->buildFilename($file, $name, $extension);
        $path = $this->publicUploadRelativePath($filename);
        $stored = Storage::disk('public')->put($path, $binary, [
            'visibility' => 'public',
            'mimetype' => $mimeType,
        ]);

        if (! $stored) {
            throw new RuntimeException('Không thể lưu ảnh tải lên.');
        }

        return [
            'path' => $path,
            'url' => $this->rootRelativeUrl(Storage::disk('public')->url($path)),
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size' => strlen($binary),
        ];
    }

    private function rootRelativeUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Không thể tạo URL ảnh đã tải lên.');
        }

        return '/'.ltrim($path, '/');
    }

    private function createImageResource(UploadedFile $file): mixed
    {
        $imageContent = file_get_contents($file->getRealPath());

        if (! is_string($imageContent) || $imageContent === '') {
            throw new RuntimeException('Không thể đọc nội dung ảnh tải lên.');
        }

        $resource = @imagecreatefromstring($imageContent);

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

    private function buildFilename(UploadedFile $file, ?string $name, string $extension): string
    {
        $baseName = $name !== null && trim($name) !== ''
            ? trim($name)
            : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $slug = Str::slug($baseName);

        if ($slug === '') {
            $slug = 'image';
        }

        return $slug.'-'.Str::lower((string) Str::uuid()).'.'.$extension;
    }
}
