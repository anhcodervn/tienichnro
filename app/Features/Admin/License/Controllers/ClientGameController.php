<?php

namespace App\Features\Admin\License\Controllers;

use App\Features\Admin\License\Requests\UploadClientGameRequest;
use App\Features\License\Services\ClientGameReleaseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientGameController extends Controller
{
    public function index(ClientGameReleaseService $releases): JsonResponse
    {
        return response()->json(['data' => $releases->catalog(true)]);
    }

    public function store(UploadClientGameRequest $request, string $kind, ClientGameReleaseService $releases): JsonResponse
    {
        $releases->save($kind, $request->validated('version'), $request->file('zip'));

        return response()->json(['message' => 'Đã cập nhật ZIP client game.', 'data' => $releases->catalog(true)]);
    }

    public function download(string $kind, ClientGameReleaseService $releases): StreamedResponse
    {
        return $releases->download($kind);
    }
}
