<?php

namespace App\Features\NroNotification\Controllers;

use App\Features\NroNotification\Requests\SaveZaloReceiveNotificationRequest;
use App\Features\NroNotification\Resources\ZaloReceiveNotificationResource;
use App\Http\Controllers\Controller;
use App\Models\ZaloReceiveNotification;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ZaloReceiveNotificationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ZaloReceiveNotificationResource::collection(ZaloReceiveNotification::query()->latest('id')->get());
    }

    public function store(SaveZaloReceiveNotificationRequest $request): ZaloReceiveNotificationResource
    {
        return new ZaloReceiveNotificationResource(ZaloReceiveNotification::query()->create($request->validated()));
    }

    public function update(SaveZaloReceiveNotificationRequest $request, ZaloReceiveNotification $receiver): ZaloReceiveNotificationResource
    {
        $receiver->update($request->validated());

        return new ZaloReceiveNotificationResource($receiver->refresh());
    }

    public function destroy(ZaloReceiveNotification $receiver): Response
    {
        $receiver->delete();

        return response()->noContent();
    }
}
