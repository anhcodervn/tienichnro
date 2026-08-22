<?php

namespace App\Features\Client\Contact\Services;

use App\Features\Reporting\Services\DiscordReportService;
use App\Models\ContactFeedback;
use App\Models\User;

class ContactService
{
    public function __construct(private readonly DiscordReportService $discordReportService) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createFeedback(?User $user, array $payload): ContactFeedback
    {
        $feedback = ContactFeedback::query()->create([
            'user_id' => $user?->id,
            'name' => $payload['name'] ?? $user?->full_name ?? $user?->username ?? null,
            'email' => $payload['email'] ?? $user?->email ?? null,
            'phone' => $payload['phone'] ?? $user?->phone ?? null,
            'subject' => (string) $payload['subject'],
            'content' => (string) $payload['content'],
            'status' => ContactFeedback::STATUS_NEW,
        ]);

        $feedback->loadMissing(['user:id,username,full_name,email,phone']);

        $this->discordReportService->queue(
            channel: 'feedback',
            title: 'Có góp ý mới cần xử lý',
            details: [
                'Mã góp ý' => $feedback->id,
                'User ID' => $feedback->user_id ?? 'guest',
                'Tiêu đề' => $feedback->subject,
                'Admin kiểm tra' => url('/admin/feedbacks'),
            ],
            dedupeKey: "feedback:{$feedback->id}:created",
        );

        return $feedback;
    }
}
