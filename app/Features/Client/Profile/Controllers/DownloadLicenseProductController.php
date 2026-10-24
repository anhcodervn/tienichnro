<?php

namespace App\Features\Client\Profile\Controllers;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadLicenseProductController extends Controller
{
    public function __invoke(License $license, string $kind): StreamedResponse
    {
        Gate::authorize('download', $license);
        abort_unless(in_array($kind, ['tool', 'update'], true), 404);
        $product = $license->product;
        $path = $product->{$kind.'_zip_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404, 'File ZIP chưa có hoặc không còn tồn tại.');

        return Storage::disk('local')->download($path, $product->product_code.'-'.$kind.'-'.($product->latest_version ?? 'latest').'.zip', [
            'Content-Type' => 'application/zip', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
