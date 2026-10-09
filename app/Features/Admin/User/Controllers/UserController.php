<?php

namespace App\Features\Admin\User\Controllers;

use App\Features\Admin\User\Actions\ListAdminUsersAction;
use App\Features\Admin\User\Actions\ListUserLogsAction;
use App\Features\Admin\User\Actions\ResetAdminUserPasswordAction;
use App\Features\Admin\User\Actions\ShowAdminUserAction;
use App\Features\Admin\User\Actions\UpdateAdminUserStatusAction;
use App\Features\Admin\User\Requests\AdminResetUserPasswordRequest;
use App\Features\Admin\User\Requests\AdminUserIndexRequest;
use App\Features\Admin\User\Requests\AdminUserRelatedListRequest;
use App\Features\Admin\User\Requests\AdminUserStatusRequest;
use App\Features\Admin\User\Resources\AdminUserDetailResource;
use App\Features\Admin\User\Resources\AdminUserResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(AdminUserIndexRequest $request, ListAdminUsersAction $action): JsonResponse
    {
        return response()->json(ApiResponse::success(data: $action->handle($request->validated())));
    }

    public function show(User $user, ShowAdminUserAction $action): JsonResponse
    {
        return response()->json(ApiResponse::success(data: AdminUserDetailResource::make($action->handle($user))->resolve()));
    }

    public function updateStatus(
        AdminUserStatusRequest $request,
        User $user,
        UpdateAdminUserStatusAction $action,
    ): JsonResponse {
        $updatedUser = $action->handle($user, $request->validated(), $this->admin($request));

        return response()->json(ApiResponse::success(
            'Cập nhật trạng thái người dùng thành công.',
            ['user' => AdminUserResource::make($updatedUser)->resolve()],
        ));
    }

    public function resetPassword(
        AdminResetUserPasswordRequest $request,
        User $user,
        ResetAdminUserPasswordAction $action,
    ): JsonResponse {
        $action->handle($user, $request->validated(), $this->admin($request), $request);

        return response()->json(ApiResponse::success(
            'Cấp lại mật khẩu người dùng thành công.',
        ));
    }

    public function logs(
        AdminUserRelatedListRequest $request,
        User $user,
        ListUserLogsAction $action,
    ): JsonResponse {
        return response()->json(ApiResponse::success(data: $action->handle($user, $request->validated())));
    }

    private function admin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
