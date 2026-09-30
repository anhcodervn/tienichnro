<?php

namespace App\Features\Admin\GameService\Controllers;

use App\Features\Admin\Affiliate\Requests\AffiliateIndexRequest;
use App\Features\Admin\Affiliate\Requests\SaveAffiliateAnnouncementRequest;
use App\Features\Admin\Affiliate\Services\AdminAffiliateService;
use App\Http\Controllers\Controller;
use App\Models\AffiliateAnnouncement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameServiceAnnouncementController extends Controller
{
    public function __construct(private readonly AdminAffiliateService $service) {}

    public function index(AffiliateIndexRequest $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->service->announcements($request->validated(), AffiliateAnnouncement::AUDIENCE_COLLABORATOR),
        ]);
    }

    public function store(SaveAffiliateAnnouncementRequest $request): JsonResponse
    {
        $announcement = $this->service->saveAnnouncement(
            null,
            $request->validated(),
            $this->user($request),
            $request,
            AffiliateAnnouncement::AUDIENCE_COLLABORATOR,
        );

        return response()->json(['status' => true, 'message' => 'Đã tạo thông báo dashboard công việc.', 'data' => $announcement], 201);
    }

    public function update(SaveAffiliateAnnouncementRequest $request, int $announcement): JsonResponse
    {
        $savedAnnouncement = $this->service->saveAnnouncement(
            $announcement,
            $request->validated(),
            $this->user($request),
            $request,
            AffiliateAnnouncement::AUDIENCE_COLLABORATOR,
        );

        return response()->json(['status' => true, 'message' => 'Đã cập nhật thông báo dashboard công việc.', 'data' => $savedAnnouncement]);
    }

    public function destroy(Request $request, int $announcement): JsonResponse
    {
        $this->service->deleteAnnouncement(
            $announcement,
            $this->user($request),
            $request,
            AffiliateAnnouncement::AUDIENCE_COLLABORATOR,
        );

        return response()->json(['status' => true, 'message' => 'Đã xóa thông báo dashboard công việc.']);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
