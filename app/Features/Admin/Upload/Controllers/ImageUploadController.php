<?php

namespace App\Features\Admin\Upload\Controllers;

use App\Exceptions\ApiException;
use App\Features\Admin\Upload\Requests\StoreImageUploadRequest;
use App\Features\Admin\Upload\Services\ImageUploadService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class ImageUploadController extends Controller
{
    public function store(StoreImageUploadRequest $request, ImageUploadService $imageUploadService): JsonResponse
    {
        try {
            $uploadedImage = $imageUploadService->store(
                $request->file('image'),
                $request->validated('name'),
            );
        } catch (RuntimeException $exception) {
            throw new ApiException($exception->getMessage(), 500, previous: $exception);
        }

        return response()->json([
            'status' => true,
            'message' => 'Tải ảnh lên thành công.',
            'data' => $uploadedImage,
        ]);
    }
}
