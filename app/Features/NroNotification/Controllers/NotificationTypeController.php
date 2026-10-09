<?php

namespace App\Features\NroNotification\Controllers;

use App\Features\NroNotification\Requests\StoreNotificationTypeRequest;
use App\Features\NroNotification\Requests\UpdateNotificationTypeRequest;
use App\Http\Controllers\Controller;
use App\Models\CodeNotify;
use App\Models\TypeNotify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class NotificationTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ['codes' => CodeNotify::query()->with('type')->orderBy('id')->get(), 'groups' => TypeNotify::query()->orderBy('id')->get()]]);
    }

    public function update(UpdateNotificationTypeRequest $request, CodeNotify $notificationType): JsonResponse
    {
        $notificationType->update($request->validated());

        return response()->json(['data' => $notificationType->fresh('type')]);
    }

    public function store(StoreNotificationTypeRequest $request): JsonResponse
    {
        $type = CodeNotify::query()->create($request->validated() + ['additional_filters' => [], 'keywords' => []]);

        return response()->json(['data' => $type->load('type')], 201);
    }

    public function destroy(CodeNotify $notificationType): Response
    {
        $notificationType->delete();

        return response()->noContent();
    }
}
