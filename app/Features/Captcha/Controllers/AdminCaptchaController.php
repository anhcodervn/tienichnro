<?php

namespace App\Features\Captcha\Controllers;

use App\Features\Admin\Wallet\Requests\AdjustWalletRequest;
use App\Features\Captcha\Requests\CaptchaRatesRequest;
use App\Features\Captcha\Services\CaptchaWalletService;
use App\Http\Controllers\Controller;
use App\Models\CaptchaSolve;
use App\Models\CaptchaTransaction;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminCaptchaController extends Controller
{
    public function wallets(Request $request): JsonResponse
    {
        return response()->json(User::query()->leftJoin('captcha_wallets', 'users.id', '=', 'captcha_wallets.user_id')
            ->select(['users.id as user_id', 'users.username', 'captcha_wallets.balance', 'captcha_wallets.reserved'])
            ->when($request->filled('user_id'), fn ($query) => $query->where('users.id', $request->integer('user_id')))
            ->orderByDesc('users.id')->paginate(20));
    }

    public function transactions(Request $request): JsonResponse
    {
        return response()->json(CaptchaTransaction::query()
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->latest('id')->paginate(20));
    }

    public function adjust(AdjustWalletRequest $request, User $user, CaptchaWalletService $wallets): JsonResponse
    {
        $values = $request->validated();
        $transaction = $wallets->adjust($user, (int) $values['amount'], $values['direction'], $values['request_id'], $values['description'], $request->user()->id);

        return response()->json(['data' => $transaction]);
    }

    public function rates(CaptchaWalletService $wallets): JsonResponse
    {
        return response()->json(['rates' => $wallets->rates()]);
    }

    public function saveRates(CaptchaRatesRequest $request, SettingStore $settings): JsonResponse
    {
        $rates = collect($request->validated('rates'))->sortBy('min_tokens')->values()->all();
        $settings->putArray('captcha.exchange_rates', $rates);

        return response()->json(['rates' => $rates]);
    }

    public function solves(Request $request): JsonResponse
    {
        return response()->json(CaptchaSolve::query()
            ->select(['id', 'user_id', 'request_id', 'source', 'status', 'result', 'http_status', 'created_at'])
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id')->paginate(20));
    }

    public function show(CaptchaSolve $solve): JsonResponse
    {
        return response()->json(['data' => $solve]);
    }

    public function image(CaptchaSolve $solve): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($solve->image_path), 404);

        return response()->file(Storage::disk('local')->path($solve->image_path), [
            'Content-Type' => 'image/webp', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
