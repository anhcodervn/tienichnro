<?php

namespace App\Features\License\Controllers;

use App\Features\License\Requests\LicenseProtocolRequest;
use App\Features\License\Services\LicenseSessionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class LicenseController extends Controller
{
    public function __construct(private readonly LicenseSessionService $sessions) {}

    public function challenge(LicenseProtocolRequest $request): JsonResponse
    {
        return $this->respond($this->sessions->challenge());
    }

    public function activate(LicenseProtocolRequest $request): JsonResponse
    {
        return $this->respond($this->sessions->activate($request));
    }

    public function transfer(LicenseProtocolRequest $request): JsonResponse
    {
        return $this->respond($this->sessions->activate($request, true));
    }

    public function heartbeat(LicenseProtocolRequest $request): JsonResponse
    {
        return $this->respond($this->sessions->sessionRequest($request, 'heartbeat'));
    }

    public function status(LicenseProtocolRequest $request): JsonResponse
    {
        return $this->respond($this->sessions->sessionRequest($request, 'status'));
    }

    public function deactivate(LicenseProtocolRequest $request): JsonResponse
    {
        return $this->respond($this->sessions->sessionRequest($request, 'deactivate'));
    }

    /** @param array<string, mixed> $data */
    private function respond(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
