<?php

namespace App\Features\Admin\User\Actions;

use App\Actions\RecordUserLogAction;
use App\Models\User;
use App\Support\MailQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetAdminUserPasswordAction
{
    public function __construct(
        private readonly RecordUserLogAction $recordUserLogAction,
        private readonly MailQueue $mailQueue,
    ) {}

    /**
     * @param  array{password:string,password_confirmation:string}  $payload
     */
    public function handle(User $user, array $payload, User $actor, Request $request): void
    {
        DB::transaction(function () use ($user, $payload): void {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->forceFill(['password' => $payload['password'], 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            $user->userSessions()->delete();
            Password::broker()->deleteToken($user);
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        }, 3);

        $actorLabel = $actor->email ?: $actor->username ?: sprintf('admin #%d', $actor->id);

        $this->recordUserLogAction->handle(
            $user,
            'admin_reset_password',
            sprintf('Mật khẩu tài khoản được admin %s cập nhật', $actorLabel),
            $request,
        );

        if (is_string($user->email) && trim($user->email) !== '') {
            $this->mailQueue->dispatch(
                to: $user->email,
                subjectText: 'Mật khẩu tài khoản đã được cập nhật',
                title: 'Mật khẩu tài khoản của bạn vừa được thay đổi',
                messageLines: [
                    'Quản trị viên vừa cập nhật mật khẩu cho tài khoản của bạn.',
                    sprintf('Thời gian: %s', now()->format('d/m/Y H:i:s')),
                    sprintf('Thực hiện bởi: %s', $actorLabel),
                    'Các phiên đăng nhập khác đã được đăng xuất để bảo mật tài khoản.',
                    'Nếu đây không phải yêu cầu của bạn, hãy liên hệ hỗ trợ ngay.',
                ],
                ctaText: 'Đăng nhập lại',
                ctaUrl: url('/login'),
            );
        }
    }
}
