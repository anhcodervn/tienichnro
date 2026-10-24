<?php

namespace App\Features\Admin\User\Controllers;

use App\Features\Admin\User\Actions\UpdateUserToolsAction;
use App\Features\Admin\User\Requests\UpdateUserToolsRequest;
use App\Http\Controllers\Controller;
use App\Models\LicenseProduct;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserToolController extends Controller
{
    public function index(User $user): JsonResponse
    {
        return response()->json(['data' => [
            'tools' => LicenseProduct::query()->orderBy('name')->get(['id', 'name', 'product_code', 'description', 'image_url', 'is_active', 'latest_version']),
            'product_ids' => $user->permittedTools()->pluck('license_products.id'),
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function update(UpdateUserToolsRequest $request, User $user, UpdateUserToolsAction $action): JsonResponse
    {
        $action->handle($user, $request->validated('product_ids'), $request);

        return response()->json(['message' => 'Đã cập nhật quyền sử dụng tool.', 'data' => ['product_ids' => $user->permittedTools()->pluck('license_products.id')]])
            ->header('Cache-Control', 'no-store, private');
    }
}
