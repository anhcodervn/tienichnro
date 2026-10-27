<?php

namespace App\Features\NroGameData\Services;

use App\Models\NroGameDataRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GameDataAssetService
{
    public const CATEGORIES = ['npcs', 'items', 'mobs', 'maps', 'classes-and-skills', 'item-options', 'skill-options', 'heads', 'bodies', 'legs'];

    public const VARIANTS = ['icon', 'image'];

    /** @return array<string, mixed> */
    public function store(UploadedFile $file, string $category, int $id, ?string $variant = null): array
    {
        if (! in_array($category, self::CATEGORIES, true) || $id < 0 || $id > 4294967295
            || ($variant !== null && ! in_array($variant, self::VARIANTS, true))) {
            throw new RuntimeException('Invalid game asset identity.');
        }
        $bytes = $file->getContent();
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)
            || $info[0] > 8000 || $info[1] > 8000 || strlen($bytes) > 10 * 1024 * 1024) {
            throw new RuntimeException('Invalid game image.');
        }
        if ($info[2] !== IMAGETYPE_PNG) {
            $bytes = $this->convertToPng($bytes);
        }
        $path = $this->path($category, $id, $variant);
        if (! Storage::disk('public')->put($path, $bytes)) {
            throw new RuntimeException('Cannot store game image.');
        }

        return ['path' => $path, 'url' => $this->url($path, $bytes), 'mime_type' => 'image/png',
            'extension' => 'png', 'size' => strlen($bytes), 'category' => $category, 'icon_id' => $id, 'variant' => $variant];
    }

    private function convertToPng(string $bytes): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Converting game assets to PNG requires PHP GD.', 503);
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new RuntimeException('Invalid game image.');
        }
        try {
            imagesavealpha($image, true);
            ob_start();
            try {
                $encoded = imagepng($image);
                $bytes = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            if (! $encoded || ! is_string($bytes)) {
                throw new RuntimeException('Cannot encode game image.');
            }

            return $bytes;
        } finally {
            imagedestroy($image);
        }
    }

    public function find(string $category, ?int $id, ?string $variant = null): ?string
    {
        if ($id === null || $id < 0 || ! in_array($category, self::CATEGORIES, true)
            || ($variant !== null && ! in_array($variant, ['image', 'icon'], true))) {
            return null;
        }
        $path = $this->path($category, $id, $variant);
        $disk = Storage::disk('public');

        return $disk->exists($path) ? $this->url($path, $disk->get($path)) : null;
    }

    public function forRecord(string $dataset, NroGameDataRecord $record, string $variant = 'icon'): ?string
    {
        $column = match ($dataset) {
            'items', 'classes-and-skills' => 'icon_id',
            'npcs' => 'id_npc', 'mobs' => 'id_mob', 'maps' => 'id_map', default => 'id_option',
        };

        $id = $record->{$column};

        return $this->find($dataset, $id, $variant)
            ?? $this->find($dataset, $id, $variant === 'icon' ? 'image' : 'icon')
            ?? $this->find($dataset, $id);
    }

    /** @return array{image: ?string, icon: ?string, parts: array<string, array{id: int, image: ?string}>, missing: list<string>} */
    public function preview(string $dataset, NroGameDataRecord $record): array
    {
        $image = $this->forRecord($dataset, $record, 'image');
        $parts = [];
        $missing = [];
        if ($dataset === 'npcs') {
            foreach (['head' => 'heads', 'body' => 'bodies', 'leg' => 'legs'] as $part => $category) {
                $id = (int) $record->{$part.'_id'};
                $url = $this->find($category, $id, 'image') ?? $this->find($category, $id, 'icon') ?? $this->find($category, $id);
                $parts[$part] = ['id' => $id, 'image' => $url];
                if ($url === null) {
                    $missing[] = $part;
                }
            }
        }

        return ['image' => $image, 'icon' => $this->forRecord($dataset, $record), 'parts' => $parts, 'missing' => $missing];
    }

    private function path(string $category, int $id, ?string $variant = null): string
    {
        return 'upload/data-game/'.$category.'/'.($variant !== null ? $variant.'/' : '').$id.'.png';
    }

    private function url(string $path, string $bytes): string
    {
        $url = parse_url(Storage::disk('public')->url($path), PHP_URL_PATH);

        return '/'.ltrim($url, '/').'?v='.substr(hash('sha256', $bytes), 0, 16);
    }
}
