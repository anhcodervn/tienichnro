<?php

namespace App\Features\Client\GameImage\Services;

use App\Models\GameImageAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Phar;
use PharData;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

class GameImageAssetService
{
    public const PANELS = ['panel-quest.png', 'panel-inventory.png', 'panel-skills.png', 'panel-pet.png', 'npc-4-magic-tree.png', 'bag.png', 'box.png', 'box-84.png'];

    /** @param array<string, mixed> $layout */
    public function saveLayout(GameImageAccount $account, array $layout, ?string $preview): void
    {
        $disk = Storage::disk('local');
        $path = $preview === null ? null : 'game-images/'.$account->id.'/previews/'.Str::uuid().'.png';
        if ($path !== null && ! $disk->put($path, base64_decode(substr($preview, 22), true))) {
            throw new RuntimeException('Không lưu được ảnh ghép.');
        }
        try {
            $old = DB::transaction(function () use ($account, $layout, $path): ?string {
                $locked = GameImageAccount::query()->lockForUpdate()->findOrFail($account->id);
                $old = $locked->preview_path;
                $locked->forceFill(['layout' => $layout, 'preview_path' => $path])->save();

                return $old;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                $disk->delete($path);
            }
            throw $exception;
        }
        if ($old !== null) {
            $disk->delete($old);
        }
        $account->refresh();
    }

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $paths
     */
    public function storeCapture(GameImageAccount $account, array $files, array $paths, int $version = 1, ?string $cname = null, ?string $claimToken = null, bool $publicUpload = false): void
    {
        if (in_array('_account.json', $paths, true)) {
            throw ValidationException::withMessages(['paths' => 'Tên _account.json được dành cho thông tin gói tải về.']);
        }
        if (array_diff(self::PANELS, $paths) !== []) {
            throw ValidationException::withMessages(['files' => 'Bộ dữ liệu phải có đầy đủ 8 ảnh panel gốc.']);
        }
        $total = 0;
        foreach ($files as $index => $file) {
            $total += $file->getSize();
            if (str_ends_with($paths[$index], '.png')) {
                $size = @getimagesize($file->getRealPath());
                if (! $size || ($size['mime'] ?? '') !== 'image/png' || $size[0] * $size[1] > 32000000) {
                    throw ValidationException::withMessages(['files' => 'Ảnh nguồn phải là PNG hợp lệ, tối đa 32 triệu điểm ảnh.']);
                }
            } elseif ($file->getMimeType() === 'image/png') {
                throw ValidationException::withMessages(['paths' => 'Đuôi tệp phải khớp với nội dung dữ liệu.']);
            }
        }
        if ($total > 134217728) {
            throw ValidationException::withMessages(['files' => 'Bộ dữ liệu tối đa 128 MB.']);
        }
        $metadataIndex = array_search('metadata.json', $paths, true);
        if (blank($cname) && $metadataIndex !== false) {
            $metadata = json_decode($files[$metadataIndex]->getContent(), true);
            $candidate = is_array($metadata) ? ($metadata['cName'] ?? $metadata['cname'] ?? null) : null;
            if (is_string($candidate) && mb_strlen(trim($candidate)) <= 100) {
                $cname = trim($candidate);
            }
        }
        $cname = filled($cname) ? trim($cname) : null;
        if ($publicUpload && ($cname === null || mb_strlen($cname) > 100 || ! preg_match('/\A[\p{L}\p{N}_-]+\z/u', $cname))) {
            throw ValidationException::withMessages(['cName' => 'Character name is not a valid folder name.']);
        }
        $diskName = $publicUpload ? 'game-image-uploads' : 'local';
        $directory = $publicUpload ? $cname.'/account-'.$account->id.'-v'.$version.'-'.Str::uuid()
            : 'game-images/'.$account->id.'/captures/'.Str::uuid();
        $disk = Storage::disk($diskName);
        try {
            foreach ($files as $index => $file) {
                if (! $disk->putFileAs($directory, $file, $paths[$index])) {
                    throw new RuntimeException('Không lưu được bộ dữ liệu ảnh.');
                }
            }
            [$old, $preview, $oldDisk, $accepted] = DB::transaction(function () use ($account, $directory, $paths, $version, $cname, $claimToken, $diskName): array {
                User::query()->lockForUpdate()->findOrFail($account->user_id);
                $locked = GameImageAccount::query()->lockForUpdate()->findOrFail($account->id);
                abort_unless($locked->capture_version === $version, 409, 'Lượt chụp không còn hiệu lực.');
                if ($claimToken !== null) {
                    abort_unless(filled($locked->capture_claim_hash) && hash_equals($locked->capture_claim_hash, hash('sha256', $claimToken)), 409, 'Capture claim is invalid.');
                }
                abort_if($locked->status === 'error', 409, 'Lượt ghép ảnh đã lỗi và hoàn tiền, không nhận ảnh đến trễ.');
                if ($locked->status === 'success' && $locked->capture_directory !== null) {
                    return [null, null, null, false];
                }
                $old = [$locked->capture_directory, $locked->preview_path, $locked->capture_disk ?? 'local'];
                $locked->forceFill(['capture_directory' => $directory, 'capture_files' => $paths, 'preview_path' => null,
                    'cname' => $cname ?? $locked->cname,
                    'capture_disk' => $diskName,
                    'share_token' => null, 'shared_layout' => null, 'share_expires_at' => null,
                    'status' => 'success', 'error_code' => null, 'error_message' => null, 'completed_at' => now()])->save();

                return [...$old, true];
            });
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);
            throw $exception;
        }
        if (! $accepted) {
            $disk->deleteDirectory($directory);
        }
        if ($old !== null) {
            Storage::disk($oldDisk)->deleteDirectory($old);
        }
        if ($preview !== null) {
            Storage::disk('local')->delete($preview);
        }
        $account->refresh();
    }

    public function storeCaptureArchive(GameImageAccount $account, UploadedFile $package, int $version = 1, ?string $cname = null, ?string $claimToken = null, bool $publicUpload = false): void
    {
        $disk = Storage::disk('local');
        $temporary = 'game-image-uploads/'.Str::uuid();
        try {
            $stored = $disk->putFileAs($temporary, $package, 'source.zip');
            if (! $stored) {
                throw new RuntimeException('Không lưu được gói dữ liệu.');
            }
            $archive = new PharData($disk->path($stored));
            if (! $archive->isFileFormat(Phar::ZIP)) {
                throw ValidationException::withMessages(['package' => 'Gói dữ liệu phải là file ZIP.']);
            }
            $prefix = 'phar://'.str_replace('\\', '/', $disk->path($stored)).'/';
            $files = [];
            $paths = [];
            $total = 0;
            foreach (new RecursiveIteratorIterator($archive) as $entry) {
                $entryName = str_replace('\\', '/', $entry->getPathname());
                $path = substr($entryName, strlen($prefix));
                $total += $entry->getSize();
                if (! str_starts_with($entryName, $prefix) || $entry->isLink() || strlen($path) > 160
                    || ! preg_match('/\A(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9_-]+\.(?:png|json|txt)\z/', $path)
                    || count($files) >= 300 || $entry->getSize() > 8388608 || $total > 134217728) {
                    throw ValidationException::withMessages(['package' => 'Gói ZIP chứa đường dẫn, loại tệp hoặc dung lượng không hợp lệ.']);
                }
                $target = $temporary.'/files/'.$path;
                if (! $disk->put($target, $entry->getContent())) {
                    throw new RuntimeException('Không đọc được bộ dữ liệu ảnh.');
                }
                $files[] = new UploadedFile($disk->path($target), basename($path), null, null, true);
                $paths[] = $path;
            }
            if (array_diff(self::PANELS, $paths) !== [] && $paths !== []) {
                $root = explode('/', $paths[0])[0].'/';
                if (count(array_filter($paths, fn (string $path): bool => str_starts_with($path, $root))) === count($paths)) {
                    $paths = array_map(fn (string $path): string => substr($path, strlen($root)), $paths);
                }
            }
            $this->storeCapture($account, $files, $paths, $version, $cname, $claimToken, $publicUpload);
        } catch (\UnexpectedValueException $exception) {
            throw ValidationException::withMessages(['package' => 'Không đọc được file ZIP.']);
        } finally {
            unset($archive);
            $disk->deleteDirectory($temporary);
        }
    }

    /** @return array<string, string>|null */
    public function sourceUrls(GameImageAccount $account, ?string $token = null): ?array
    {
        if ($account->capture_directory === null) {
            return null;
        }
        $urls = [];
        foreach ($account->capture_files ?? [] as $path) {
            if (str_ends_with($path, '.png')) {
                $parameters = ['account' => $account->id, 'path' => $path];
                $urls[$path] = $token === null ? route('tools.game-image.source', $parameters)
                    : URL::temporarySignedRoute('tools.game-image.shared.source', $account->share_expires_at, [...$parameters, 'token' => $token]);
            }
        }

        return $urls;
    }

    public function sourcePath(GameImageAccount $account, string $path): string
    {
        abort_unless($account->capture_directory !== null && in_array($path, $account->capture_files ?? [], true) && str_ends_with($path, '.png'), 404);
        $file = $account->capture_directory.'/'.$path;
        $disk = Storage::disk($account->capture_disk ?? 'local');
        abort_unless($disk->exists($file), 404);

        return $disk->path($file);
    }

    public function captureZip(GameImageAccount $account): string
    {
        abort_unless($account->capture_directory !== null && filled($account->capture_files), 404, 'Tài khoản chưa có bộ dữ liệu ảnh.');
        $disk = Storage::disk('local');
        $sources = Storage::disk($account->capture_disk ?? 'local');
        $disk->makeDirectory('game-image-downloads');
        $path = $disk->path('game-image-downloads/'.Str::uuid().'.zip');
        try {
            $zip = new PharData($path, 0, null, Phar::ZIP);
            foreach ($account->capture_files as $file) {
                $source = $account->capture_directory.'/'.$file;
                abort_unless($sources->exists($source), 404, 'Bộ dữ liệu ảnh chưa đầy đủ.');
                $zip->addFile($sources->path($source), $file);
            }
            $zip->addFromString('_account.json', json_encode(['id' => $account->id, 'username' => $account->username, 'server' => $account->server, 'cName' => $account->cname, 'capture_version' => $account->capture_version,
                'files' => $account->capture_files], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            unset($zip);
        } catch (Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }
            throw $exception;
        }

        return $path;
    }
}
