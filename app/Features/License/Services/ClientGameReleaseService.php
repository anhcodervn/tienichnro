<?php

namespace App\Features\License\Services;

use App\Models\Setting;
use App\Support\SettingStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ClientGameReleaseService
{
    public const KINDS = ['original', 'update'];

    public function __construct(private readonly SettingStore $settings) {}

    /** @return array<string, mixed> */
    public function catalog(bool $admin = false): array
    {
        $releases = [];
        foreach (self::KINDS as $kind) {
            $release = $this->settings->getArray('client_game_'.$kind);
            $releases[$kind] = $release === [] ? null : [
                ...Arr::except($release, ['path']),
                'available' => Storage::disk('local')->exists($release['path']),
                'download_url' => route($admin ? 'admin-api.license.client-game.download' : 'license.client-game.download', ['kind' => $kind], false),
            ];
        }

        return $releases;
    }

    public function save(string $kind, string $version, UploadedFile $file): void
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        $path = $file->store('client-game/'.$kind, 'local');
        if (! $path) {
            throw new RuntimeException('Không thể lưu file ZIP. Vui lòng thử lại.');
        }

        try {
            $release = [
                'path' => $path,
                'version' => $version,
                'name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'uploaded_at' => now()->toISOString(),
            ];
            DB::transaction(function () use ($kind, $release): void {
                $key = 'client_game_'.$kind;
                Setting::query()->firstOrCreate(['key' => $key], ['value' => '{}', 'type' => 'json']);
                $setting = Setting::query()->where('key', $key)->lockForUpdate()->firstOrFail();
                $previous = $this->settings->decode($setting, []);
                $this->settings->putArray($key, $release);
                if (! empty($previous['path'])) {
                    DB::afterCommit(fn () => rescue(fn () => Storage::disk('local')->delete($previous['path'])));
                }
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function download(string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        $release = $this->settings->getArray('client_game_'.$kind);
        abort_unless(! empty($release['path']) && Storage::disk('local')->exists($release['path']), 404);

        return Storage::disk('local')->download($release['path'], 'client-game-'.$kind.'-'.$release['version'].'.zip', [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
