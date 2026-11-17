<?php

namespace App\Features\Client\GameImage\Controllers;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\GameImage\Services\GameImageAssetService;
use App\Http\Controllers\Controller;
use App\Models\GameImageAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GameImageShareController extends Controller
{
    public function store(Request $request, GameImageAccount $account, ToolAvailabilityService $tools): JsonResponse
    {
        $this->authorizeOwner($request, $account);
        abort_unless(($tools->find('game_image')['is_enabled'] ?? true), 403);
        $token = Str::random(40);
        $expires = now()->addDays(7);
        $account->forceFill([
            'share_token' => hash('sha256', $token),
            'shared_layout' => $account->layout ?? ['item_ids' => [], 'show_details' => true, 'positions' => [], 'groups' => []],
            'share_expires_at' => $expires,
        ])->save();

        return response()->json(['url' => URL::temporarySignedRoute('tools.game-image.shared', $expires, ['account' => $account->id, 'token' => $token]), 'expires_at' => $expires->toIso8601String()]);
    }

    public function show(GameImageAccount $account, string $token, GameImageAssetService $assets): Response
    {
        abort_unless($account->shared_layout !== null && $account->share_expires_at?->isFuture()
            && hash_equals((string) $account->share_token, hash('sha256', $token)), 404);

        return response()->view('pages.tools.game-image-edit', [
            'readOnly' => true,
            'account' => ['id' => $account->id, 'username' => 'Bản chia sẻ', 'server' => '', 'layout' => $account->shared_layout,
                'capture_urls' => $assets->sourceUrls($account, $token)],
        ])->header('Referrer-Policy', 'no-referrer');
    }

    public function source(GameImageAccount $account, string $token, string $path, GameImageAssetService $assets): BinaryFileResponse
    {
        abort_unless($account->shared_layout !== null && $account->share_expires_at?->isFuture()
            && hash_equals((string) $account->share_token, hash('sha256', $token)), 404);

        return response()->file($assets->sourcePath($account, $path), ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, GameImageAccount $account): JsonResponse
    {
        $this->authorizeOwner($request, $account);
        $account->forceFill(['share_token' => null, 'shared_layout' => null, 'share_expires_at' => null])->save();

        return response()->json(['revoked' => true]);
    }

    private function authorizeOwner(Request $request, GameImageAccount $account): void
    {
        abort_unless($request->user()?->status === 'active' && $account->user_id === $request->user()->id, 403);
    }
}
