<?php

namespace App\Features\NroGameData\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GameEffectDecoderService
{
    /** @return array<string, mixed> */
    public function binary(string $bytes): array
    {
        $offset = 0;
        $read = function (int $size, bool $signed = true) use ($bytes, &$offset): int {
            if ($offset + $size > strlen($bytes)) {
                throw ValidationException::withMessages(['binary' => 'Truncated effect data.']);
            }
            $value = unpack($size === 1 ? 'C' : 'n', substr($bytes, $offset, $size))[1];
            $offset += $size;

            return $signed && $value >= (1 << ($size * 8 - 1)) ? $value - (1 << ($size * 8)) : $value;
        };
        $count = $read(1);
        if ($count < 1 || $count > 127) {
            throw ValidationException::withMessages(['binary' => 'Invalid region count.']);
        }
        $regions = [];
        for ($i = 0; $i < $count; $i++) {
            $regions[] = ['id' => $read(1), 'x' => $read(1, false), 'y' => $read(1, false), 'width' => $read(1, false), 'height' => $read(1, false)];
        }
        $count = $read(2);
        if ($count < 1 || $count > 512) {
            throw ValidationException::withMessages(['binary' => 'Invalid frame count.']);
        }
        $frames = [];
        for ($i = 0; $i < $count; $i++) {
            $pieces = [];
            $pieceCount = $read(1);
            if ($pieceCount < 1 || $pieceCount > 127) {
                throw ValidationException::withMessages(['binary' => 'Invalid piece count.']);
            }
            for ($j = 0; $j < $pieceCount; $j++) {
                $pieces[] = ['dx' => $read(2), 'dy' => $read(2), 'imageId' => $read(1), 'drawOrder' => $j];
            }
            $frames[] = ['pieces' => $pieces];
        }
        $count = $read(2);
        if ($count < 1 || $count > 8192) {
            throw ValidationException::withMessages(['binary' => 'Invalid sequence length.']);
        }
        $sequence = [];
        for ($i = 0; $i < $count; $i++) {
            $sequence[] = $read(2);
        }

        return ['regions' => $regions, 'frames' => $frames, 'sequence' => $sequence,
            'bytesConsumed' => $offset, 'trailingHex' => bin2hex(substr($bytes, $offset))];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed> */
    public function normalize(array $data, int $width, int $height, int $effectId, int $tickRate): array
    {
        Validator::make($data, [
            'regions' => ['required', 'array', 'list', 'min:1', 'max:127'],
            'regions.*.id' => ['required', 'integer', 'distinct', 'between:-128,255'],
            'regions.*.x' => ['required', 'integer', 'min:0'], 'regions.*.y' => ['required', 'integer', 'min:0'],
            'regions.*.width' => ['required', 'integer', 'min:1'], 'regions.*.height' => ['required', 'integer', 'min:1'],
            'frames' => ['required', 'array', 'list', 'min:1', 'max:512'],
            'frames.*.pieces' => ['required', 'array', 'list', 'min:1', 'max:127'],
            'frames.*.pieces.*.imageId' => ['required', 'integer'],
            'frames.*.pieces.*.dx' => ['required', 'integer', 'between:-32768,32767'],
            'frames.*.pieces.*.dy' => ['required', 'integer', 'between:-32768,32767'],
            'sequence' => ['required', 'array', 'list', 'min:1', 'max:8192'],
            'sequence.*' => ['required', 'integer', 'min:0', 'max:'.((is_array($data['frames'] ?? null) ? count($data['frames']) : 0) - 1)],
        ])->validate();
        $regions = [];
        foreach ($data['regions'] as $region) {
            if ($width < $region['x'] + $region['width'] || $height < $region['y'] + $region['height']) {
                throw ValidationException::withMessages(['animation' => 'Region extends outside the original PNG.']);
            }
            $regions[(int) $region['id']] = ['id' => (int) $region['id'], 'x' => (int) $region['x'], 'y' => (int) $region['y'], 'width' => (int) $region['width'], 'height' => (int) $region['height']];
        }
        ksort($regions, SORT_NUMERIC);
        $bounds = ['minX' => PHP_INT_MAX, 'minY' => PHP_INT_MAX, 'maxX' => PHP_INT_MIN, 'maxY' => PHP_INT_MIN];
        $frames = [];
        foreach ($data['frames'] as $frame) {
            $pieces = [];
            foreach ($frame['pieces'] as $index => $piece) {
                $region = $regions[$piece['imageId']] ?? null;
                if (! $region) {
                    throw ValidationException::withMessages(['animation' => 'Piece references an unknown region ID.']);
                }
                $dx = (int) $piece['dx'];
                $dy = (int) $piece['dy'];
                $pieces[] = ['imageId' => (int) $piece['imageId'], 'dx' => $dx, 'dy' => $dy, 'drawOrder' => $index];
                $bounds['minX'] = min($bounds['minX'], $dx);
                $bounds['minY'] = min($bounds['minY'], $dy);
                $bounds['maxX'] = max($bounds['maxX'], $dx + $region['width']);
                $bounds['maxY'] = max($bounds['maxY'], $dy + $region['height']);
            }
            $frames[] = ['pieces' => $pieces];
        }
        $canvasWidth = $bounds['maxX'] - $bounds['minX'];
        $canvasHeight = $bounds['maxY'] - $bounds['minY'];
        if ($canvasWidth > 2048 || $canvasHeight > 2048) {
            throw ValidationException::withMessages(['animation' => 'Effect bounds exceed 2048 pixels.']);
        }
        if ($effectId === 17 && ($width !== 167 || $height !== 168 || count($regions) !== 10 || count($frames) !== 4
            || array_filter($frames, fn (array $frame): bool => count($frame['pieces']) !== 11)
            || array_map('intval', $data['sequence']) !== [0, 0, 0, 1, 1, 1, 2, 2, 2, 3, 3, 3]
            || $bounds !== ['minX' => -72, 'minY' => -110, 'maxX' => 74, 'maxY' => 87])) {
            throw ValidationException::withMessages(['animation' => 'Effect 17 does not match the original sheet, frames, sequence or bounds.']);
        }

        return ['effectId' => $effectId, 'regions' => array_values($regions), 'frames' => $frames,
            'sequence' => array_map('intval', $data['sequence']), 'bounds' => $bounds, 'tickRate' => $tickRate,
            'width' => $canvasWidth, 'height' => $canvasHeight, 'originX' => -$bounds['minX'], 'originY' => -$bounds['minY'],
            'trans' => 0, 'bytesConsumed' => $data['bytesConsumed'] ?? null, 'trailingHex' => $data['trailingHex'] ?? null];
    }
}
