<?php

namespace App\Features\Admin\Upload\Controllers;

use App\Exceptions\ApiException;
use App\Features\Admin\Upload\Requests\ImportImageUploadRequest;
use App\Features\Admin\Upload\Requests\StoreImageUploadRequest;
use App\Features\Admin\Upload\Services\Base64ImageUploadService;
use App\Features\Admin\Upload\Services\ImageUploadService;
use App\Features\Admin\Upload\Services\RemoteImageUploadService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class ImageUploadController extends Controller
{
    public function import(ImportImageUploadRequest $request, RemoteImageUploadService $remoteImageUploadService): JsonResponse
    {
        try {
            $uploadedImage = $remoteImageUploadService->store($request->validated('url'));
        } catch (RuntimeException $exception) {
            throw new ApiException($exception->getMessage(), $exception->getCode() === 503 ? 503 : 422, previous: $exception);
        }

        return response()->json([
            'status' => true,
            'message' => 'Đã tải ảnh về máy chủ thành công.',
            'data' => $uploadedImage,
        ]);
    }

    public function store(
        StoreImageUploadRequest $request,
        ImageUploadService $imageUploadService,
        Base64ImageUploadService $base64ImageUploadService,
        RemoteImageUploadService $remoteImageUploadService,
    ): JsonResponse {
        try {
            $name = $request->validated('name');
            $gameAsset = $request->filled('category') ? $request->safe()->only(['category', 'icon_id', 'variant']) : null;
            $uploadedImage = match (true) {
                $request->hasFile('image') => $imageUploadService->store($request->file('image'), $name, $gameAsset),
                $request->filled('base64') => $base64ImageUploadService->store($request->validated('base64'), $name, $gameAsset),
                default => $remoteImageUploadService->store($request->validated('url'), $name, $gameAsset),
            };
        } catch (RuntimeException $exception) {
            throw new ApiException($exception->getMessage(), $exception->getCode() === 503 ? 503 : ($request->hasFile('image') ? 500 : 422), previous: $exception);
        }

        return response()->json([
            'status' => true,
            'message' => 'Tải ảnh lên thành công.',
            'data' => $uploadedImage,
        ]);
    }
}
