<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class QueuedResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120];

    public function __construct(
        public string $token,
    ) {
        $this->onQueue('mails');
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = (string) ($notifiable->getEmailForPasswordReset() ?? '');
        $resetUrl = URL::route('password.reset', [
            'token' => $this->token,
            'email' => $email,
        ], false);

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu Nạp Carot')
            ->view('emails.reset-password', [
                'name' => $notifiable->name ?? $notifiable->username ?? 'bạn',
                'resetUrl' => $resetUrl,
                'expireMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            ]);
    }
}
