<?php

namespace App\Features\NroNotification\Controllers;

use App\Features\NroNotification\Requests\RegisterZaloReceiveNotificationRequest;
use App\Features\NroNotification\Resources\ZaloReceiveNotificationResource;
use App\Http\Controllers\Controller;
use App\Models\NroServer;
use App\Models\ZaloReceiveNotification;
use Illuminate\Support\Facades\DB;

class InternalZaloReceiveNotificationController extends Controller
{
    public function __invoke(RegisterZaloReceiveNotificationRequest $request): ZaloReceiveNotificationResource
    {
        $data = $request->validated();
        $receiver = DB::transaction(function () use ($data): ZaloReceiveNotification {
            NroServer::query()->where('server_code', $data['char_server'])->lockForUpdate()->firstOrFail();
            $identity = [
                'zalo_id' => $data['zalo_id'], 'char_name' => $data['char_name'],
                'char_server' => $data['char_server'], 'box_zalo_id' => $data['box_zalo_id'] ?? null,
            ];

            return ZaloReceiveNotification::query()->updateOrCreate($identity, ['type_receive' => $data['type_receive']]);
        });

        return new ZaloReceiveNotificationResource($receiver);
    }
}
