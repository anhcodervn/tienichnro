<?php

namespace App\Features\Admin\User\Controllers;

use App\Features\Admin\User\Actions\AdjustUserWalletAction;
use App\Features\Admin\User\Actions\ListAdminUsersAction;
use App\Features\Admin\User\Actions\ListUserLogsAction;
use App\Features\Admin\User\Actions\ListUserWalletTransactionsAction;
use App\Features\Admin\User\Actions\ResetAdminUserPasswordAction;
use App\Features\Admin\User\Actions\ShowAdminUserAction;
use App\Features\Admin\User\Actions\UpdateAdminUserStatusAction;
use App\Features\Admin\User\Requests\AdminResetUserPasswordRequest;
use App\Features\Admin\User\Requests\AdminUserIndexRequest;
use App\Features\Admin\User\Requests\AdminUserRelatedListRequest;
use App\Features\Admin\User\Requests\AdminUserStatusRequest;
use App\Features\Admin\User\Requests\AdminWalletAdjustRequest;
use App\Features\Admin\User\Requests\QuickSetUserPackagePricesRequest;
use App\Features\Admin\User\Requests\UpsertUserPackagePriceRequest;
use App\Features\Admin\User\Resources\AdminUserDetailResource;
use App\Features\Admin\User\Resources\AdminUserResource;
use App\Features\Admin\User\Services\UserPackagePriceAdminService;
use App\Http\Controllers\Controller;
use App\Models\GlobalTopupPackage;
use App\Models\TopupPackage;
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
            ['user' => AdminUserResource::make($updatedUser->load('wallet'))->resolve()],
        ));
    }

    public function walletAdjust(
        AdminWalletAdjustRequest $request,
        User $user,
        AdjustUserWalletAction $action,
    ): JsonResponse {
        return response()->json(ApiResponse::success(
            'Điều chỉnh số dư ví thành công.',
            $action->handle($user, $request->validated(), $this->admin($request), $request),
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

    public function prices(User $user, UserPackagePriceAdminService $service): JsonResponse
    {
        return response()->json(ApiResponse::success(data: $service->catalog($user)));
    }

    public function quickSetPrices(
        QuickSetUserPackagePricesRequest $request,
        User $user,
        UserPackagePriceAdminService $service,
    ): JsonResponse {
        $service->quickSet($user, $request->validated());

        return response()->json(ApiResponse::success(
            'Đã áp dụng nhanh chiết khấu cho thành viên.',
            $service->catalog($user),
        ));
    }

    public function updatePrice(
        UpsertUserPackagePriceRequest $request,
        User $user,
        TopupPackage $topupPackage,
        UserPackagePriceAdminService $service,
    ): JsonResponse {
        $service->save($user, $topupPackage, $request->validated());

        return response()->json(ApiResponse::success(
            'Đã cập nhật chiết khấu riêng cho thành viên.',
            $service->catalog($user),
        ));
    }

    public function deletePrice(User $user, TopupPackage $topupPackage, UserPackagePriceAdminService $service): JsonResponse
    {
        $service->delete($user, $topupPackage);

        return response()->json(ApiResponse::success(
            'Đã đưa thành viên về giá mặc định của website.',
            $service->catalog($user),
        ));
    }

    public function updateGlobalPrice(
        UpsertUserPackagePriceRequest $request,
        User $user,
        GlobalTopupPackage $globalTopupPackage,
        UserPackagePriceAdminService $service,
    ): JsonResponse {
        $service->saveGlobal($user, $globalTopupPackage, $request->validated());

        return response()->json(ApiResponse::success(
            'Đã cập nhật chiết khấu Global cho thành viên.',
            $service->catalog($user),
        ));
    }

    public function deleteGlobalPrice(
        User $user,
        GlobalTopupPackage $globalTopupPackage,
        UserPackagePriceAdminService $service,
    ): JsonResponse {
        $service->deleteGlobal($user, $globalTopupPackage);

        return response()->json(ApiResponse::success(
            'Đã xóa chiết khấu Global của thành viên.',
            $service->catalog($user),
        ));
    }

    public function walletTransactions(
        AdminUserRelatedListRequest $request,
        User $user,
        ListUserWalletTransactionsAction $action,
    ): JsonResponse {
        return response()->json(ApiResponse::success(data: $action->handle($user, $request->validated())));
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
