<?php

namespace App\Features\Admin\Upload\Services;

use GdImage;
use RuntimeException;

class ImageWatermarkService
{
    private const TEXT = 'tienichnro.com';

    public function apply(GdImage $image): void
    {
        $font = 5;
        $tile = imagecreatetruecolor(imagefontwidth($font) * strlen(self::TEXT) + 12, imagefontheight($font) + 12);
        if ($tile === false) {
            throw new RuntimeException('Không thể tạo watermark ảnh.');
        }
        $rotated = null;
        $scaled = null;
        try {
            imagealphablending($tile, false);
            imagesavealpha($tile, true);
            $transparent = imagecolorallocatealpha($tile, 0, 0, 0, 127);
            imagefill($tile, 0, 0, $transparent);
            imagestring($tile, $font, 7, 7, self::TEXT, imagecolorallocatealpha($tile, 0, 0, 0, 108));
            imagestring($tile, $font, 6, 6, self::TEXT, imagecolorallocatealpha($tile, 255, 255, 255, 96));
            $rotated = imagerotate($tile, 25, $transparent);
            if ($rotated === false) {
                throw new RuntimeException('Không thể xoay watermark ảnh.');
            }
            imagesavealpha($rotated, true);
            $width = imagesx($image);
            $height = imagesy($image);
            $tileWidth = max(1, min(180, (int) round($width * 0.45)));
            $tileHeight = max(1, (int) round(imagesy($rotated) * $tileWidth / imagesx($rotated)));
            $scaled = imagecreatetruecolor($tileWidth, $tileHeight);
            if ($scaled === false) {
                throw new RuntimeException('Không thể tạo lớp watermark ảnh.');
            }
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
            imagecopyresampled($scaled, $rotated, 0, 0, 0, 0, $tileWidth, $tileHeight, imagesx($rotated), imagesy($rotated));

            imagealphablending($image, true);
            $stepX = $tileWidth + max(8, (int) round($tileWidth * 0.55));
            $stepY = $tileHeight + max(8, (int) round($tileWidth * 0.3));
            $row = 0;
            for ($y = -(int) round($tileHeight / 2); $y < $height; $y += $stepY) {
                $startX = $row++ % 2 === 0 ? -(int) round($tileWidth / 2) : (int) round($stepX / 2);
                for ($x = $startX; $x < $width; $x += $stepX) {
                    imagecopy($image, $scaled, $x, $y, 0, 0, $tileWidth, $tileHeight);
                }
            }
            imagesavealpha($image, true);
        } finally {
            imagedestroy($tile);
            if ($rotated instanceof GdImage) {
                imagedestroy($rotated);
            }
            if ($scaled instanceof GdImage) {
                imagedestroy($scaled);
            }
        }
    }
}
