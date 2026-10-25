<?php

namespace App\Features\License\Controllers;

use App\Features\License\Services\ClientGameReleaseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClientGameDownloadController extends Controller
{
    public function index(Request $request, ClientGameReleaseService $releases): JsonResponse
    {
        abort_unless($request->user()->status === 'active', 403);

        return response()->json(['success' => true, 'data' => $releases->catalog()]);
    }

    public function download(Request $request, string $kind, ClientGameReleaseService $releases): StreamedResponse|JsonResponse
    {
        abort_unless($request->user()->status === 'active', 403);

        try {
            return $releases->download($kind);
        } catch (NotFoundHttpException) {
            return response()->json(['success' => false, 'code' => 'FILE_NOT_FOUND', 'message' => 'FILE_NOT_FOUND'], 404);
        }
    }
}
