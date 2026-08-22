<?php

namespace App\Features\Support\Services;

use App\Models\SupportMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DiscordSupportNotifierService
{
    public function send(SupportMessage $message): void
    {
        $webhookUrl = trim((string) config('services.discord.channels.support', ''));

        if ($webhookUrl === '') {
            return;
        }

        $message->loadMissing('conversation.user');
        $conversation = $message->conversation;
        $user = $conversation->user;
        $preview = Str::of($message->message)
            ->replace(['@everyone', '@here'], ['＠everyone', '＠here'])
            ->limit(500)
            ->toString();

        Http::connectTimeout(3)
            ->timeout(7)
            ->acceptJson()
            ->post($webhookUrl, [
                'username' => (string) config('services.discord.bot_name', 'Nạp Carot Monitor'),
                'avatar_url' => (string) config('services.discord.bot_avatar_url', ''),
                'allowed_mentions' => ['parse' => []],
                'embeds' => [[
                    'title' => 'Tin nhắn hỗ trợ mới',
                    'description' => $preview,
                    'color' => 0x2563EB,
                    'fields' => [
                        ['name' => 'Conversation ID', 'value' => (string) $conversation->id, 'inline' => true],
                        ['name' => 'Message ID', 'value' => (string) $message->id, 'inline' => true],
                        ['name' => 'User ID', 'value' => (string) $user->id, 'inline' => true],
                        ['name' => 'Username', 'value' => (string) $user->username, 'inline' => true],
                    ],
                    'url' => rtrim((string) config('app.url'), '/')."/admin/support?conversation={$conversation->id}",
                ]],
            ])
            ->throw();
    }
}
