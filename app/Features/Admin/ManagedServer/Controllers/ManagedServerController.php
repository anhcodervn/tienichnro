<?php

namespace App\Features\Admin\ManagedServer\Controllers;

use App\Features\Admin\ManagedServer\Requests\ManagedServerRequest;
use App\Features\Admin\ManagedServer\Requests\NapCarotCredentialsRequest;
use App\Features\Admin\ManagedServer\Requests\ServerIndexRequest;
use App\Features\Admin\ManagedServer\Services\NapCarotServerService;
use App\Http\Controllers\Controller;
use App\Models\ManagedServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagedServerController extends Controller
{
    public function index(ServerIndexRequest $request, string $type): JsonResponse
    {
        $filters = $request->validated();

        return response()->json(ManagedServer::query()->where('type', $type)
            ->when(filled($filters['q'] ?? null), fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$filters['q'].'%')->orWhere('server_id', 'like', '%'.$filters['q'].'%')))
            ->when(isset($filters['active']), fn ($query) => $query->where('is_active', $filters['active']))
            ->orderBy('sort_order')->orderBy('id')->paginate(20));
    }

    public function store(ManagedServerRequest $request, string $type): JsonResponse
    {
        return response()->json(['data' => ManagedServer::query()->create([...$request->validated(), 'type' => $type])], 201);
    }

    public function update(ManagedServerRequest $request, string $type, ManagedServer $server): JsonResponse
    {
        abort_unless($server->type === $type, 404);
        $server->update($request->validated());

        return response()->json(['data' => $server->refresh()]);
    }

    public function destroy(Request $request, string $type, ManagedServer $server): JsonResponse
    {
        $this->ensureAdmin($request);
        abort_unless($server->type === $type, 404);
        $server->delete();

        return response()->json(['status' => true]);
    }

    public function configuration(Request $request, NapCarotServerService $service): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json(['data' => $service->configuration()]);
    }

    public function configure(NapCarotCredentialsRequest $request, NapCarotServerService $service): JsonResponse
    {
        return response()->json(['data' => $service->save($request->validated())]);
    }

    public function sync(Request $request, NapCarotServerService $service): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json(['data' => $service->sync()]);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin' && $request->user()?->status === 'active', 403);
    }
}
