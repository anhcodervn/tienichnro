<?php

namespace App\Features\NroNotification\Controllers;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\NroNotification\Requests\IngestNotifyRequest;
use App\Features\NroNotification\Requests\NotifyIndexRequest;
use App\Features\NroNotification\Requests\VerifyNotificationAccessRequest;
use App\Features\NroNotification\Resources\NotifyResource;
use App\Features\NroNotification\Services\NotificationAccessService;
use App\Features\NroNotification\Services\NotificationStreamSlotsService;
use App\Features\NroNotification\Services\NroNotificationFeedService;
use App\Features\NroNotification\Services\NroNotificationService;
use App\Http\Controllers\Controller;
use App\Models\Boss;
use App\Models\CodeNotify;
use App\Models\NroServer;
use Generator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotifyController extends Controller
{
    public function __construct(private readonly ToolAvailabilityService $services, private readonly NotificationAccessService $access) {}

    public function index(NotifyIndexRequest $request, NroNotificationService $service): AnonymousResourceCollection
    {
        if (! $request->routeIs('admin.*')) {
            $this->ensureAvailable();
        }

        return NotifyResource::collection($service->paginate($request->routeIs('admin.*') ? $request->validated() : $this->access->filters($request, $request->publicFilters())));
    }

    public function store(IngestNotifyRequest $request, NroNotificationService $service): JsonResponse
    {
        $result = $service->ingest($request->validated());

        return response()->json(['status' => $result['status'], 'duplicate' => $result['duplicate'], 'data' => $result['notify'] ? new NotifyResource($result['notify']) : null], $result['status'] === 'created' && ! $result['duplicate'] ? 201 : 200);
    }

    public function page(NotifyIndexRequest $request, NroNotificationFeedService $feed): View|JsonResponse
    {
        $filters = $this->access->filters($request, $request->publicFilters());
        $visibleFilters = Arr::except($filters, ['_preview_cutoff', '_preview_since']);
        $realtime = $this->access->realtime($request);
        $tool = $this->services->interaction('game_notifications');
        if ($request->expectsJson()) {
            $this->ensureAvailable();
        }
        $snapshot = $tool['is_enabled'] ? $feed->snapshot($filters) : null;
        if ($request->expectsJson()) {
            return response()->json(['data' => $snapshot, 'filters' => $visibleFilters,
                'url' => route('nro.notifies.page', $visibleFilters), 'stream_url' => $realtime ? route('nro.notifies.stream', $visibleFilters) : '', 'realtime' => $realtime]);
        }

        return view('pages.nro.notifies', ['snapshot' => $snapshot, 'filters' => $visibleFilters, 'service' => $tool,
            'realtime' => $realtime, 'turnstileSiteKey' => $this->access->siteKey(),
            'limit' => $filters['limit'],
            'notificationTypes' => CodeNotify::query()->orderBy('id')->get(),
            'servers' => NroServer::query()->where('is_active', true)->orderBy('sort_order')->orderBy('server_code')->get(),
            'bosses' => Boss::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->orderBy('id')->get()]);
    }

    public function stream(NotifyIndexRequest $request, NroNotificationFeedService $feed, NotificationStreamSlotsService $slots): StreamedResponse
    {
        $this->ensureAvailable();
        abort_unless($this->access->realtime($request), 403, 'Đăng nhập và xác minh để xem thông báo trực tiếp.');
        $filters = $request->publicFilters();
        if ($request->hasSession()) {
            $request->session()->save();
        }

        $locks = $slots->acquire($request);

        return response()->eventStream(function () use ($request, $feed, $filters, $slots, $locks): Generator {
            try {
                foreach ($feed->events($filters) as $event) {
                    if (! $this->access->realtime($request)) {
                        yield new StreamedEvent('access-expired', ['message' => 'Quyền xem trực tiếp đã hết hạn. Vui lòng xác minh lại.']);

                        return;
                    }
                    yield $event;
                }
            } finally {
                $slots->release($locks);
            }
        }, endStreamWith: null);
    }

    public function verify(VerifyNotificationAccessRequest $request): JsonResponse
    {
        $this->ensureAvailable();
        $this->access->verify($request, $request->validated('cf-turnstile-response'));

        return response()->json(['status' => true, 'message' => 'Đã mở quyền xem thông báo trực tiếp trong 15 phút.']);
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
