<?php

namespace App\Features\NroNotification\Controllers;

use App\Features\NroNotification\Requests\DeleteZaloReceiveNotificationRequest;
use App\Features\NroNotification\Requests\FindZaloReceiveNotificationRequest;
use App\Features\NroNotification\Requests\RegisterZaloReceiveNotificationRequest;
use App\Features\NroNotification\Resources\ZaloReceiveNotificationResource;
use App\Http\Controllers\Controller;
use App\Models\NroServer;
use App\Models\ZaloReceiveNotification;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class InternalZaloReceiveNotificationController extends Controller
{
    public function index(FindZaloReceiveNotificationRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();
        $query = ZaloReceiveNotification::query()->where('zalo_id', $data['zalo_id']);
        if (isset($data['char_name'])) {
            $query->whereRaw('LOWER(char_name) = ?', [$data['char_name']]);
        }
        if (isset($data['char_server'])) {
            $query->where('char_server', $data['char_server']);
        }
        if (array_key_exists('box_zalo_id', $data)) {
            $query->where('box_zalo_id', $data['box_zalo_id']);
        }

        $receivers = $query->latest('id')->get();

        return ZaloReceiveNotificationResource::collection($receivers)
            ->additional(['exists' => $receivers->isNotEmpty()]);
    }

    public function destroy(DeleteZaloReceiveNotificationRequest $request): Response
    {
        $data = $request->validated();
        DB::transaction(function () use ($data): void {
            NroServer::query()->where('server_code', $data['char_server'])->lockForUpdate()->firstOrFail();
            ZaloReceiveNotification::query()
                ->where('zalo_id', $data['zalo_id'])
                ->where('char_server', $data['char_server'])
                ->where('box_zalo_id', $data['box_zalo_id'] ?? null)
                ->whereRaw('LOWER(char_name) = ?', [$data['char_name']])
                ->delete();
        });

        return response()->noContent();
    }

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
