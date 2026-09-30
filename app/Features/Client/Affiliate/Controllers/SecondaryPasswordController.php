<?php

namespace App\Features\Client\Affiliate\Controllers;

use App\Features\Client\Affiliate\Requests\UnlockSecondaryPasswordRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\GameServiceSecondaryAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecondaryPasswordController extends Controller
{
    public function __construct(private readonly GameServiceSecondaryAuth $secondaryAuth) {}

    public function show(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'status' => true,
            'data' => [
                'configured' => $this->secondaryAuth->isConfigured($user),
                'unlocked' => $this->secondaryAuth->isUnlocked($user, $request),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(UnlockSecondaryPasswordRequest $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Đã mở khóa bằng mật khẩu C2.',
            'data' => $this->secondaryAuth->unlock(
                $this->user($request),
                $request->string('password')->toString(),
            ),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
