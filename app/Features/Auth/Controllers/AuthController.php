<?php

namespace App\Features\Auth\Controllers;

use App\Actions\RecordUserLogAction;
use App\Exceptions\ApiException;
use App\Features\Auth\Requests\ForgotPasswordRequest;
use App\Features\Auth\Requests\LoginRequest;
use App\Features\Auth\Requests\RegisterRequest;
use App\Features\Auth\Services\GoogleAuthService;
use App\Features\Client\Wallet\Services\WalletService;
use App\Features\Reporting\Services\DiscordReportService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MailQueue;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly RecordUserLogAction $recordUserLogAction,
        private readonly MailQueue $mailQueue,
        private readonly GoogleAuthService $googleAuthService,
        private readonly DiscordReportService $discordReportService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'feature' => 'Auth',
            'message' => 'Auth feature index',
        ]);
    }

    public function login(LoginRequest $request): JsonResponse|RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();

        if (! $user instanceof User) {
            throw new ApiException('Không thể xác thực tài khoản.', 500);
        }

        $this->touchLastLogin($user, $request);
        $this->recordUserLogAction->handle($user, 'login', 'Đăng nhập hệ thống', $request);

        if (! $request->expectsJson()) {
            return redirect()->intended(route('account.index'));
        }

        return response()->json([
            'status' => true,
            'message' => 'Đăng nhập thành công.',
            'redirect' => url('/'),
            'user' => $this->userPayload($user),
        ]);
    }

    public function apiLogin(LoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $user = User::query()
            ->where($request->loginField(), $request->normalizedLogin())
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            $request->hitRateLimit();

            throw new ApiException('Thông tin đăng nhập không chính xác.', 422, [
                'errors' => [
                    'login' => ['Thông tin đăng nhập không chính xác.'],
                ],
            ]);
        }

        $request->clearRateLimit();
        $this->touchLastLogin($user, $request);
        $this->recordUserLogAction->handle($user, 'api_login', 'Đăng nhập API', $request);

        $token = $user->createToken($request->userAgent() ?: 'auth-api')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Đăng nhập thành công.',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'] ?? $validated['full_name'] ?? null,
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);

        $this->walletService->createWallet($user);
        $this->recordUserLogAction->handle($user, 'register', 'Đăng ký tài khoản', $request);

        if (is_string($user->email) && $user->email !== '') {
            $this->mailQueue->dispatch(
                to: $user->email,
                subjectText: 'Đăng ký tài khoản thành công',
                title: 'Chào mừng bạn đến với hệ thống',
                messageLines: [
                    'Tài khoản của bạn đã được tạo thành công.',
                    'Bạn có thể đăng nhập và bắt đầu sử dụng các tính năng ngay bây giờ.',
                ],
                ctaText: 'Đăng nhập',
                ctaUrl: route('auth.login'),
            );
        }

        event(new Registered($user));
        $this->queueRegistrationReport($user, 'form');

        if (! $request->expectsJson()) {
            return redirect()->route('auth.login')->with('success', 'Đăng ký thành công. Vui lòng kiểm tra email để xác minh tài khoản.');
        }

        return response()->json([
            'status' => true,
            'message' => 'Đăng ký thành công.',
            'redirect' => route('auth.login'),
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker()->sendResetLink($request->only('email'));

        return response()->json([
            'status' => true,
            'message' => 'Nếu email tồn tại trong hệ thống, liên kết đặt lại mật khẩu đã được gửi.',
        ]);
    }

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        if (! $this->googleAuthService->isConfigured()) {
            return redirect()
                ->route('auth.login')
                ->with('auth_google_error', 'Đăng nhập Google chưa được cấu hình.');
        }

        $state = $this->googleAuthService->generateState();
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away($this->googleAuthService->authorizationUrl($state));
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_oauth_state');
        $receivedState = $request->string('state')->toString();

        if (
            ! is_string($expectedState)
            || $expectedState === ''
            || $receivedState === ''
            || ! hash_equals($expectedState, $receivedState)
        ) {
            return redirect()
                ->route('auth.login')
                ->with('auth_google_error', 'Phiên đăng nhập Google không hợp lệ. Vui lòng thử lại.');
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('auth.login')
                ->with('auth_google_error', 'Bạn đã hủy hoặc từ chối đăng nhập bằng Google.');
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect()
                ->route('auth.login')
                ->with('auth_google_error', 'Google không trả về mã xác thực hợp lệ.');
        }

        try {
            $googleUser = $this->googleAuthService->fetchUser($code);
            $isNewUser = false;

            $user = User::query()
                ->where('google_id', $googleUser['id'])
                ->orWhere('email', $googleUser['email'])
                ->first();

            if ($user instanceof User) {
                if ($user->status !== 'active') {
                    return redirect()
                        ->route('auth.login')
                        ->with('auth_google_error', 'Tài khoản của bạn hiện không thể đăng nhập.');
                }

                $user->forceFill([
                    'google_id' => $googleUser['id'],
                    'email' => $googleUser['email'],
                    'full_name' => $googleUser['name'],
                    'avatar' => $googleUser['avatar'],
                    'email_verified_at' => $googleUser['email_verified'] ? ($user->email_verified_at ?: now()) : $user->email_verified_at,
                ])->save();
            } else {
                $isNewUser = true;
                $user = User::query()->create([
                    'username' => Str::of($googleUser['email'])->before('@')->slug('')->value() ?: null,
                    'email' => $googleUser['email'],
                    'full_name' => $googleUser['name'],
                    'avatar' => $googleUser['avatar'],
                    'google_id' => $googleUser['id'],
                    'email_verified_at' => $googleUser['email_verified'] ? now() : null,
                    'password' => Str::random(64),
                    'status' => 'active',
                    'role' => 'user',
                ]);
            }

            Auth::guard('web')->login($user, true);
            $request->session()->regenerate();

            $this->touchLastLogin($user, $request);
            $this->recordUserLogAction->handle($user, 'google_login', 'Đăng nhập bằng Google', $request);

            if ($user->hasVerifiedEmail()) {
                event(new Verified($user));
            }

            if ($isNewUser) {
                $this->queueRegistrationReport($user, 'google');
            }

            return redirect('/');
        } catch (Throwable $exception) {
            return redirect()
                ->route('auth.login')
                ->with('auth_google_error', $exception->getMessage() ?: 'Không thể đăng nhập bằng Google.');
        }
    }

    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->exists) {
            $this->recordUserLogAction->handle($user, 'logout', 'Đăng xuất hệ thống', $request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => true,
                'message' => 'Đăng xuất thành công.',
                'redirect' => route('login'),
            ]);
        }

        return redirect()->route('login');
    }

    private function queueRegistrationReport(User $user, string $registrationType): void
    {
        $this->discordReportService->queue(
            channel: 'activity',
            title: 'Người dùng đăng ký mới',
            details: [
                'Loại đăng ký' => $registrationType,
                'User ID' => $user->id,
                'Username' => $user->username,
                'Trạng thái' => $user->status,
                'Vai trò' => $user->role,
            ],
            dedupeKey: "user:{$user->id}:registered",
        );
    }

    /**
     * @return array{
     *     id:int,
     *     username:string,
     *     email:?string,
     *     phone:?string,
     *     full_name:?string,
     *     role:mixed,
     *     status:mixed,
     *     wallet:array{id:int,user_id:int,type:string,balance:string,hold_balance:string,total_recharge:string,total_spent:string,created_at:?string,updated_at:?string}
     * }
     */
    protected function userPayload(User $user): array
    {
        return [
            ...$user->only([
                'id',
                'username',
                'email',
                'phone',
                'full_name',
                'role',
                'status',
            ]),
            'wallet' => $this->walletService->getWalletInfo($user),
        ];
    }

    protected function touchLastLogin(User $user, Request $request): void
    {
        $previousLoginIp = is_string($user->last_login_ip) ? trim($user->last_login_ip) : '';
        $currentLoginIp = is_string($request->ip()) ? trim((string) $request->ip()) : '';

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $currentLoginIp !== '' ? $currentLoginIp : $request->ip(),
        ])->save();

        if (
            $previousLoginIp !== ''
            && $currentLoginIp !== ''
            && $previousLoginIp !== $currentLoginIp
            && is_string($user->email)
            && trim($user->email) !== ''
        ) {
            $this->mailQueue->dispatch(
                to: $user->email,
                subjectText: 'Đăng nhập từ IP mới',
                title: 'Phát hiện đăng nhập từ IP mới',
                messageLines: [
                    sprintf('Tài khoản của bạn vừa đăng nhập từ IP mới: %s', $currentLoginIp),
                    sprintf('IP đăng nhập trước đó: %s', $previousLoginIp),
                    sprintf('Thời gian: %s', now()->format('d/m/Y H:i:s')),
                    sprintf('Thiết bị: %s', (string) ($request->userAgent() ?: 'Không xác định')),
                ],
                ctaText: 'Vào hệ thống',
                ctaUrl: url('/'),
            );
        }
    }
}
