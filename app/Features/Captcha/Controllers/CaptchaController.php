<?php

namespace App\Features\Captcha\Controllers;

use App\Features\Captcha\Requests\CaptchaHistoryRequest;
use App\Features\Captcha\Requests\PurchaseCaptchaRequest;
use App\Features\Captcha\Requests\SolveCaptchaRequest;
use App\Features\Captcha\Requests\TryCaptchaRequest;
use App\Features\Captcha\Services\CaptchaDemoService;
use App\Features\Captcha\Services\CaptchaSolverService;
use App\Features\Captcha\Services\CaptchaWalletService;
use App\Features\Client\Wallet\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Models\CaptchaApiKey;
use App\Models\CaptchaSolve;
use App\Models\CaptchaTransaction;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaptchaController extends Controller
{
    public function index(Request $request, CaptchaWalletService $wallets, WalletService $mainWallets, CaptchaSolverService $solver, CaptchaDemoService $demo): View
    {
        $user = $request->user();
        if ($user instanceof User) {
            $solver->expire($user);
        }

        return view('pages.tools.captcha', [
            'rates' => $wallets->rates(),
            'turnstileSiteKey' => $demo->siteKey(),
            'wallet' => $user ? $wallets->wallet($user) : null,
            'mainBalance' => $user ? $mainWallets->wallet($user)->balance : 0,
            'apiKey' => $user ? CaptchaApiKey::query()->where('user_id', $user->id)->first() : null,
        ]);
    }

    public function demo(TryCaptchaRequest $request, CaptchaDemoService $demo): JsonResponse
    {
        $data = $demo->solve($request, $request->validated('image'), $request->validated('cf-turnstile-response'));

        return response()->json($data, $data['status'] ? 200 : 502)->header('Cache-Control', 'no-store, private');
    }

    public function purchase(PurchaseCaptchaRequest $request, CaptchaWalletService $wallets): JsonResponse
    {
        $values = $request->validated();
        $transaction = $wallets->purchase($request->user(), (int) $values['tokens'], (int) $values['unit_price'], $values['request_id']);

        return response()->json(['data' => $transaction, 'balance' => $wallets->wallet($request->user())->balance]);
    }

    public function key(Request $request): JsonResponse
    {
        abort_unless($request->user()?->status === 'active', 403);
        $token = 'nrocap_'.Str::random(64);
        DB::transaction(function () use ($request, $token): void {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            CaptchaApiKey::query()->updateOrCreate(['user_id' => $request->user()->id], [
                'token_hash' => hash('sha256', $token), 'prefix' => substr($token, 0, 14), 'last_used_at' => null,
            ]);
        }, 3);

        return response()->json(['token' => $token])->header('Cache-Control', 'no-store, private');
    }

    public function revoke(Request $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            CaptchaApiKey::query()->where('user_id', $request->user()->id)->delete();
        }, 3);

        return response()->json(['message' => 'Đã thu hồi API token.']);
    }

    public function solve(SolveCaptchaRequest $request, CaptchaSolverService $solver): JsonResponse
    {
        abort_unless($request->user()?->status === 'active', 403);
        $values = $request->validated();
        $solve = $solver->solve($request->user(), $values['image'], (string) Str::uuid(), $request->is('api/*') ? 'api' : 'demo');
        $status = match ($solve->status) {
            'processing' => 202, 'success' => 200, default => 502
        };

        $data = [
            'status' => $solve->status === 'success',
            'code' => $solve->result,
            'time' => ($solve->duration_ms ?? 0) / 1000,
        ];
        if ($solve->status !== 'success') {
            $data['message'] = match (true) {
                $solve->status === 'processing' => 'Captcha đang được xử lý. Vui lòng chờ kết quả.',
                ($solve->response['error'] ?? null) === 'provider_unavailable' => 'Không thể kết nối bộ giải captcha. Vui lòng thử lại sau. Lượt này không bị trừ token.',
                ($solve->response['error'] ?? null) === 'invalid_response' => 'Bộ giải captcha trả về dữ liệu không hợp lệ. Vui lòng thử lại sau. Lượt này không bị trừ token.',
                ($solve->response['error'] ?? null) === 'processing_expired' => 'Lượt giải đã hết thời gian xử lý. Vui lòng thử lại. Lượt này không bị trừ token.',
                $solve->http_status !== null && ($solve->http_status < 200 || $solve->http_status >= 300) => 'Dịch vụ giải captcha đang gặp lỗi. Vui lòng thử lại sau. Lượt này không bị trừ token.',
                default => 'Chưa giải được captcha từ ảnh này. Vui lòng thử ảnh khác. Lượt này không bị trừ token.',
            };
        }

        return response()->json($data, $status);
    }

    public function history(CaptchaHistoryRequest $request, CaptchaWalletService $wallets): JsonResponse
    {
        $filters = $request->validated();

        return response()->json([
            'balance' => $wallets->wallet($request->user())->balance,
            'solves' => CaptchaSolve::query()->where('user_id', $request->user()->id)
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
                ->when(isset($filters['solves_search']), fn ($query) => $query->where('result', 'like', '%'.$filters['solves_search'].'%'))
                ->select(['id', 'request_id', 'source', 'status', 'result', 'duration_ms', 'created_at'])
                ->latest('id')->paginate(20, pageName: 'solves_page')->withQueryString(),
            'transactions' => CaptchaTransaction::query()->where('user_id', $request->user()->id)
                ->when($filters['direction'] ?? null, fn ($query, $direction) => $query->where('direction', $direction))
                ->when(isset($filters['transactions_search']), fn ($query) => $query->where('description', 'like', '%'.$filters['transactions_search'].'%'))
                ->select(['id', 'direction', 'amount', 'description', 'balance_after', 'created_at'])
                ->latest('id')->paginate(20, pageName: 'transactions_page')->withQueryString(),
        ]);
    }
}
