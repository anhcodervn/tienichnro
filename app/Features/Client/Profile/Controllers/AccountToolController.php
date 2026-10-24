<?php

namespace App\Features\Client\Profile\Controllers;

use App\Features\Client\Profile\Resources\AccountToolResource;
use App\Http\Controllers\Controller;
use App\Models\LicenseProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountToolController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->status === 'active', 403, 'Tài khoản đã bị khóa.');

        return AccountToolResource::collection($request->user()->permittedTools()->where('is_active', true)->orderBy('name')->get());
    }

    public function download(Request $request, LicenseProduct $product, string $kind): StreamedResponse
    {
        abort_unless($request->user()->status === 'active' && $product->is_active
            && $request->user()->permittedTools()->whereKey($product->id)->exists(), 403);
        abort_unless(in_array($kind, ['tool', 'update'], true), 404);
        $path = $product->{$kind.'_zip_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $product->product_code.'-'.$kind.'-'.($product->latest_version ?? 'latest').'.zip', [
            'Content-Type' => 'application/zip', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
