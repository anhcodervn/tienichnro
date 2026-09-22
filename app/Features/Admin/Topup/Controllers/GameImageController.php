<?php

namespace App\Features\Admin\Topup\Controllers;

use App\Exceptions\ApiException;
use App\Features\Admin\Topup\Requests\StoreGameImageRequest;
use App\Features\Admin\Topup\Services\GameImageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class GameImageController extends Controller
{
    public function store(StoreGameImageRequest $request, GameImageService $gameImageService): JsonResponse
    {
        try {
            $uploadedImage = $gameImageService->store(
                $request->file('image'),
                $request->validated('name'),
            );
        } catch (RuntimeException $exception) {
            throw new ApiException($exception->getMessage(), 500, previous: $exception);
        }

        return response()->json([
            'status' => true,
            'message' => 'Tải ảnh game lên thành công.',
            'data' => $uploadedImage,
        ]);
    }
}
