<?php

namespace App\Features\NroGameData\Services;

use App\Support\SettingStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Throwable;

class GameEffectService
{
    public function __construct(private readonly SettingStore $settings, private readonly GameEffectDecoderService $decoder) {}

    /** @return array<string, mixed>|null */
    public function forRecord(string $dataset, int $id): ?array
    {
        $effectId = $dataset === 'npcs' && $id === 24 ? 17 : null;
        if ($effectId === null) {
            return null;
        }

        return ['effectId' => $effectId, ...$this->settings->getArray('game_effect_'.$effectId)];
    }

    /** @return array<string, mixed> */
    public function install(int $effectId, UploadedFile $sheet, ?UploadedFile $animation, ?UploadedFile $binary, int $tickRate): array
    {
        $image = getimagesize($sheet->getRealPath());
        $fromBinary = $binary ? $this->decoder->binary($binary->getContent()) : null;
        try {
            $fromJson = $animation ? json_decode($animation->getContent(), true, 64, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            throw ValidationException::withMessages(['animation' => 'Invalid effect JSON.']);
        }
        if ($fromJson !== null && ! is_array($fromJson)) {
            throw ValidationException::withMessages(['animation' => 'Effect JSON must be an object.']);
        }
        $data = $this->decoder->normalize($fromBinary ?? $fromJson ?? [], $image[0], $image[1], $effectId, $tickRate);
        if ($fromBinary && $fromJson) {
            $json = $this->decoder->normalize($fromJson, $image[0], $image[1], $effectId, $tickRate);
            foreach (['regions', 'frames', 'sequence'] as $key) {
                if ($json[$key] !== $data[$key]) {
                    throw ValidationException::withMessages(['animation' => 'JSON and binary animation do not match.']);
                }
            }
        }
        $directory = 'game-effects/'.$effectId.'/'.Str::uuid();
        $disk = Storage::disk('public');
        $files = ['sheet.png' => $sheet->getContent(), 'animation.json' => json_encode($data, JSON_THROW_ON_ERROR)];
        if ($animation) {
            $files['source.json'] = $animation->getContent();
        }
        if ($binary) {
            $files['source.data.bin'] = $binary->getContent();
        }
        try {
            foreach ($files as $name => $contents) {
                if (! $disk->put($directory.'/'.$name, $contents)) {
                    throw new RuntimeException('Cannot store effect resources.');
                }
            }
            $descriptor = ['sheetUrl' => $disk->url($directory.'/sheet.png'), 'dataUrl' => $disk->url($directory.'/animation.json'),
                'sheetSha256' => hash('sha256', $files['sheet.png']), 'width' => $data['width'], 'height' => $data['height'], 'tickRate' => $tickRate];
            $this->settings->putArray('game_effect_'.$effectId, $descriptor);

            return ['effectId' => $effectId, ...$descriptor];
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);
            throw $exception;
        }
    }
}
