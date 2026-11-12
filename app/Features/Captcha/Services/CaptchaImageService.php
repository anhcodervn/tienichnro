<?php

namespace App\Features\Captcha\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CaptchaImageService
{
    /** @return array{bytes: string, base64: string, hash: string} */
    public function decode(string $image): array
    {
        $base64 = preg_replace('#^data:image/(?:png|jpeg|webp);base64,#i', '', $image);
        $bytes = base64_decode($base64, true);
        $size = $bytes !== false ? @getimagesizefromstring($bytes) : false;
        if ($bytes === false || strlen($bytes) > 2 * 1024 * 1024 || ! $size
            || ! in_array($size['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)
            || $size[0] > 2048 || $size[1] > 2048) {
            throw ValidationException::withMessages(['image' => 'Ảnh phải là base64 PNG, JPEG hoặc WebP, tối đa 2MB và 2048 × 2048 pixel.']);
        }

        return ['bytes' => $bytes, 'base64' => base64_encode($bytes), 'hash' => hash('sha256', $bytes)];
    }

    public function store(string $bytes): string
    {
        abort_unless(function_exists('imagewebp'), 503, 'Máy chủ cần bật PHP GD có hỗ trợ WebP.');
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw ValidationException::withMessages(['image' => 'Không đọc được ảnh.']);
        }
        try {
            ob_start();
            $success = imagewebp($image, null, 90);
            $webp = ob_get_clean();
        } finally {
            imagedestroy($image);
        }
        abort_unless($success && is_string($webp) && $webp !== '', 503, 'Không thể chuyển ảnh sang WebP.');
        $path = 'captcha/'.now()->format('Y/m/d').'/'.Str::uuid().'.webp';
        abort_unless(Storage::disk('local')->put($path, $webp), 503, 'Không thể lưu ảnh captcha.');

        return $path;
    }
}
