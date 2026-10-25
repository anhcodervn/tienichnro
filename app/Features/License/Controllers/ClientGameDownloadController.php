<?php

namespace App\Features\License\Controllers;

use App\Features\License\Requests\LicenseProtocolRequest;
use App\Features\License\Services\ClientGameReleaseService;
use App\Features\License\Services\LicenseFailure;
use App\Features\License\Services\LicenseSessionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClientGameDownloadController extends Controller
{
    public function __construct(private readonly LicenseSessionService $sessions) {}

    public function index(LicenseProtocolRequest $request, ClientGameReleaseService $releases): JsonResponse
    {
        $this->sessions->sessionRequest($request, 'status');

        return response()->json(['success' => true, 'data' => $releases->catalog()]);
    }

    public function download(LicenseProtocolRequest $request, string $kind, ClientGameReleaseService $releases): StreamedResponse
    {
        $this->sessions->sessionRequest($request, 'status');

        try {
            return $releases->download($kind);
        } catch (NotFoundHttpException) {
            throw new LicenseFailure('FILE_NOT_FOUND', 404);
        }
    }
}
