<?php

namespace App\Features\Admin\AuditLog\Controllers;

use App\Features\Admin\AuditLog\Requests\AdminAuditLogIndexRequest;
use App\Features\Admin\AuditLog\Services\AdminAuditLogService;
use App\Http\Controllers\Controller;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    public function __construct(private readonly AdminAuditLogService $auditLogService) {}

    public function index(AdminAuditLogIndexRequest $request): JsonResponse
    {
        return response()->json(ApiResponse::success(data: $this->auditLogService->paginate($request->validated())));
    }
}
