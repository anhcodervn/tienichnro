<?php

namespace App\Features\Admin\License\Services;

use App\Models\LicenseProduct;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProductReleaseService
{
    /** @param array<string, mixed> $data */
    public function save(array $data, ?LicenseProduct $product = null): LicenseProduct
    {
        $newPaths = [];
        $attributes = Arr::except($data, ['tool_zip', 'update_zip']);
        try {
            foreach (['tool', 'update'] as $kind) {
                $file = $data[$kind.'_zip'] ?? null;
                if (! $file instanceof UploadedFile) {
                    continue;
                }
                $path = $file->store('license-products', 'local');
                if (! $path) {
                    throw new RuntimeException('Không thể lưu file ZIP. Vui lòng thử lại.');
                }
                $newPaths[] = $path;
                $attributes[$kind.'_zip_path'] = $path;
                $attributes[$kind.'_zip_name'] = mb_substr($file->getClientOriginalName(), 0, 255);
                $attributes[$kind.'_zip_size'] = $file->getSize();
                $attributes[$kind.'_zip_sha256'] = hash_file('sha256', $file->getRealPath());
            }

            return DB::transaction(function () use ($attributes, $product): LicenseProduct {
                $product = $product ? LicenseProduct::query()->lockForUpdate()->findOrFail($product->id) : new LicenseProduct;
                $oldPaths = [];
                foreach (['tool', 'update'] as $kind) {
                    if (array_key_exists($kind.'_zip_path', $attributes) && $product->{$kind.'_zip_path'}) {
                        $oldPaths[] = $product->{$kind.'_zip_path'};
                    }
                }
                $product->forceFill($attributes)->save();
                DB::afterCommit(function () use ($oldPaths): void {
                    if ($oldPaths !== []) {
                        rescue(fn () => Storage::disk('local')->delete($oldPaths));
                    }
                });

                return $product->refresh();
            }, 3);
        } catch (Throwable $exception) {
            if ($newPaths !== []) {
                Storage::disk('local')->delete($newPaths);
            }
            throw $exception;
        }
    }
}
