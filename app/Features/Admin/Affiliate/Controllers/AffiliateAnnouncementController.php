<?php

namespace App\Features\Admin\Affiliate\Controllers;

use App\Features\Admin\Affiliate\Requests\AffiliateIndexRequest;
use App\Features\Admin\Affiliate\Requests\SaveAffiliateAnnouncementRequest;
use App\Features\Admin\Affiliate\Services\AdminAffiliateService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateAnnouncementController extends Controller
{
    public function __construct(private readonly AdminAffiliateService $service) {}

    public function index(AffiliateIndexRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->announcements($request->validated())]);
    }

    public function store(SaveAffiliateAnnouncementRequest $request): JsonResponse
    {
        $announcement = $this->service->saveAnnouncement(null, $request->validated(), $this->user($request), $request);

        return response()->json(['status' => true, 'message' => 'Đã tạo thông báo cộng tác viên.', 'data' => $announcement], 201);
    }

    public function update(SaveAffiliateAnnouncementRequest $request, int $announcement): JsonResponse
    {
        $savedAnnouncement = $this->service->saveAnnouncement($announcement, $request->validated(), $this->user($request), $request);

        return response()->json(['status' => true, 'message' => 'Đã cập nhật thông báo cộng tác viên.', 'data' => $savedAnnouncement]);
    }

    public function destroy(Request $request, int $announcement): JsonResponse
    {
        $this->service->deleteAnnouncement($announcement, $this->user($request), $request);

        return response()->json(['status' => true, 'message' => 'Đã xóa thông báo cộng tác viên.']);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
