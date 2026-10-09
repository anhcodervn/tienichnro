<?php

namespace App\Features\NroNotification\Controllers;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\NroNotification\Requests\IngestNotifyRequest;
use App\Features\NroNotification\Requests\NotifyIndexRequest;
use App\Features\NroNotification\Resources\NotifyResource;
use App\Features\NroNotification\Services\NroNotificationFeedService;
use App\Features\NroNotification\Services\NroNotificationService;
use App\Http\Controllers\Controller;
use App\Models\Boss;
use App\Models\CodeNotify;
use App\Models\NroServer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotifyController extends Controller
{
    public function __construct(private readonly ToolAvailabilityService $services) {}

    public function index(NotifyIndexRequest $request, NroNotificationService $service): AnonymousResourceCollection
    {
        if (! $request->routeIs('admin.*')) {
            $this->ensureAvailable();
        }

        return NotifyResource::collection($service->paginate($request->routeIs('admin.*') ? $request->validated() : $request->publicFilters()));
    }

    public function store(IngestNotifyRequest $request, NroNotificationService $service): JsonResponse
    {
        $result = $service->ingest($request->validated());

        return response()->json(['status' => $result['status'], 'duplicate' => $result['duplicate'], 'data' => $result['notify'] ? new NotifyResource($result['notify']) : null], $result['status'] === 'created' && ! $result['duplicate'] ? 201 : 200);
    }

    public function page(NotifyIndexRequest $request, NroNotificationFeedService $feed): View|JsonResponse
    {
        $filters = $request->publicFilters();
        $tool = $this->services->interaction('game_notifications');
        if ($request->expectsJson()) {
            $this->ensureAvailable();
        }
        $snapshot = $tool['is_enabled'] ? $feed->snapshot($filters) : null;
        if ($request->expectsJson()) {
            return response()->json(['data' => $snapshot, 'filters' => $filters,
                'url' => route('nro.notifies.page', $filters), 'stream_url' => route('nro.notifies.stream', $filters)]);
        }

        return view('pages.nro.notifies', ['snapshot' => $snapshot, 'filters' => $filters, 'service' => $tool,
            'limit' => $filters['limit'],
            'notificationTypes' => CodeNotify::query()->orderBy('id')->get(),
            'servers' => NroServer::query()->where('is_active', true)->orderBy('sort_order')->orderBy('server_code')->get(),
            'bosses' => Boss::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->orderBy('id')->get()]);
    }

    public function stream(NotifyIndexRequest $request, NroNotificationFeedService $feed): StreamedResponse
    {
        $this->ensureAvailable();
        $filters = $request->publicFilters();
        if ($request->hasSession()) {
            $request->session()->save();
        }

        return response()->eventStream(fn () => $feed->events($filters), endStreamWith: null);
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => ['servers' => NroServer::query()->where('is_active', true)->orderBy('sort_order')->orderBy('server_code')->get()]]);
    }

    private function ensureAvailable(): void
    {
        $tool = $this->services->interaction('game_notifications');
        if (! $tool['is_enabled']) {
            throw new HttpResponseException(response()->json([
                'message' => $tool['maintenance_message'], 'service_maintenance' => true,
            ], 503, ['Retry-After' => '60', 'Cache-Control' => 'no-store']));
        }
    }
}
