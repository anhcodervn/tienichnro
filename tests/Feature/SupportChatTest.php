<?php

use App\Events\SupportMessageCreated;
use App\Events\SupportMessagesRead;
use App\Features\Support\Services\DiscordSupportNotifierService;
use App\Jobs\SendSupportMessageToDiscord;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

function supportConversationFor(User $user): SupportConversation
{
    return SupportConversation::factory()->for($user)->create();
}

test('guest cannot access client or admin support APIs', function () {
    $this->getJson('/api/client/support')->assertUnauthorized();
    $this->getJson('/api/admin-api/support/conversations')->assertUnauthorized();
});

test('regular user cannot access admin support API', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/admin-api/support/conversations')
        ->assertForbidden();
});

test('client support message limiter is scoped per user for ten seconds and one minute', function (): void {
    $user = User::factory()->create();
    $request = HttpRequest::create('/api/client/support/messages', 'POST');
    $request->setUserResolver(fn (): User => $user);
    $resolver = RateLimiter::limiter('support-user-message');

    expect($resolver)->not->toBeNull();

    $limits = $resolver($request);

    expect(collect($limits)->pluck('maxAttempts')->all())->toBe([1, 6])
        ->and(collect($limits)->pluck('decaySeconds')->all())->toBe([10, 60])
        ->and(collect($limits)->pluck('key')->all())->toBe([
            "user:{$user->id}:ten-seconds",
            "user:{$user->id}:minute",
        ])
        ->and(Route::getRoutes()->getByName('client.support.messages.store')?->gatherMiddleware())
        ->toContain('throttle:support-user-message');
});

test('user must wait ten seconds before sending another support message', function (): void {
    $user = User::factory()->create();
    Event::fake();
    Queue::fake();

    $this->actingAs($user)
        ->postJson('/api/client/support/messages', ['message' => 'Tin thứ nhất'])
        ->assertCreated();

    $limitedResponse = $this->actingAs($user)
        ->postJson('/api/client/support/messages', ['message' => 'Tin gửi quá nhanh'])
        ->assertTooManyRequests()
        ->assertJsonPath('status', false);

    expect((int) $limitedResponse->headers->get('Retry-After'))->toBeGreaterThanOrEqual(1)
        ->and((int) $limitedResponse->json('data.retry_after'))->toBeGreaterThanOrEqual(1);

    $this->travel(10)->seconds();

    $this->actingAs($user)
        ->postJson('/api/client/support/messages', ['message' => 'Tin sau thời gian chờ'])
        ->assertCreated();
});

test('user only sees and sends messages in their own conversation', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $otherConversation = supportConversationFor($secondUser);
    SupportMessage::factory()->for($otherConversation, 'conversation')->create([
        'sender_id' => $secondUser->id,
        'message' => 'Tin nhắn riêng của user khác',
    ]);

    Event::fake();
    Queue::fake();

    $this->actingAs($firstUser)
        ->getJson('/api/client/support')
        ->assertOk()
        ->assertJsonPath('data.conversation', null)
        ->assertJsonMissing(['message' => 'Tin nhắn riêng của user khác']);

    $this->actingAs($firstUser)
        ->postJson('/api/client/support/messages', ['message' => 'Tin nhắn của tôi'])
        ->assertCreated()
        ->assertJsonPath('data.message.sender_role', SupportMessage::ROLE_USER);

    expect(SupportConversation::query()->whereBelongsTo($firstUser)->count())->toBe(1)
        ->and(SupportMessage::query()->whereBelongsTo($otherConversation, 'conversation')->count())->toBe(1);
});

test('sending messages stores sender role and reuses one conversation per user', function () {
    $user = User::factory()->create();
    Event::fake();
    Queue::fake();

    $this->actingAs($user)->postJson('/api/client/support/messages', ['message' => 'Tin thứ nhất'])->assertCreated();
    $this->travel(10)->seconds();
    $this->actingAs($user)->postJson('/api/client/support/messages', ['message' => 'Tin thứ hai'])->assertCreated();

    $conversation = SupportConversation::query()->whereBelongsTo($user)->firstOrFail();

    expect(SupportConversation::query()->whereBelongsTo($user)->count())->toBe(1)
        ->and($conversation->messages()->count())->toBe(2)
        ->and($conversation->messages()->pluck('sender_role')->unique()->all())->toBe([SupportMessage::ROLE_USER]);
});

test('admin can proactively message a user without an existing conversation', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();
    Event::fake();
    Queue::fake();

    $this->actingAs($admin)
        ->postJson('/api/admin-api/support/conversations', [
            'user_id' => $user->id,
            'message' => 'Admin chủ động hỗ trợ',
        ])
        ->assertCreated()
        ->assertJsonPath('data.conversation.user.id', $user->id)
        ->assertJsonPath('data.message.sender_role', SupportMessage::ROLE_ADMIN);

    expect(SupportConversation::query()->whereBelongsTo($user)->count())->toBe(1);
});

test('message cursor pages return newest twenty in render order without duplicates', function () {
    $user = User::factory()->create();
    $conversation = supportConversationFor($user);

    foreach (range(1, 25) as $number) {
        SupportMessage::factory()->for($conversation, 'conversation')->create([
            'sender_id' => $user->id,
            'message' => "Tin {$number}",
            'created_at' => now()->addSeconds($number),
        ]);
    }

    $firstPage = $this->actingAs($user)->getJson('/api/client/support')->assertOk();
    $firstIds = collect($firstPage->json('data.messages'))->pluck('id')->all();
    $cursor = $firstPage->json('data.meta.next_cursor');

    expect($firstIds)->toHaveCount(20)
        ->and($firstIds)->toBe(collect($firstIds)->sort()->values()->all())
        ->and($firstIds)->toBe(range(6, 25))
        ->and($cursor)->not->toBeNull();

    $secondPage = $this->actingAs($user)->getJson('/api/client/support?cursor='.urlencode((string) $cursor))->assertOk();
    $secondIds = collect($secondPage->json('data.messages'))->pluck('id')->all();

    expect($secondIds)->toBe(range(1, 5))
        ->and(array_intersect($firstIds, $secondIds))->toBe([])
        ->and($secondPage->json('data.meta.has_more'))->toBeFalse();
});

test('mark read only updates unread incoming messages in the selected conversation', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $conversation = supportConversationFor($user);
    $otherConversation = supportConversationFor($otherUser);
    $incoming = SupportMessage::factory()->for($conversation, 'conversation')->create([
        'sender_id' => $admin->id,
        'sender_role' => SupportMessage::ROLE_ADMIN,
    ]);
    $outgoing = SupportMessage::factory()->for($conversation, 'conversation')->create([
        'sender_id' => $user->id,
        'sender_role' => SupportMessage::ROLE_USER,
    ]);
    $otherIncoming = SupportMessage::factory()->for($otherConversation, 'conversation')->create([
        'sender_id' => $admin->id,
        'sender_role' => SupportMessage::ROLE_ADMIN,
    ]);

    Event::fake([SupportMessagesRead::class]);

    $this->actingAs($user)
        ->postJson('/api/client/support/read')
        ->assertOk()
        ->assertJsonPath('data.updated', 1)
        ->assertJsonPath('data.stats.user_unread', 0);

    expect($incoming->refresh()->read_at)->not->toBeNull()
        ->and($outgoing->refresh()->read_at)->toBeNull()
        ->and($otherIncoming->refresh()->read_at)->toBeNull();

    Event::assertDispatched(SupportMessagesRead::class, fn (SupportMessagesRead $event): bool => $event->messageIds === [$incoming->id]);
});

test('admin unread count increases for user messages and returns to zero after reading', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();
    Event::fake();
    Queue::fake();

    $sendResponse = $this->actingAs($user)
        ->postJson('/api/client/support/messages', ['message' => 'Cần admin hỗ trợ'])
        ->assertCreated()
        ->assertJsonPath('data.stats.admin_unread', 1);

    $conversationId = (int) $sendResponse->json('data.conversation.id');

    $this->actingAs($admin)
        ->postJson("/api/admin-api/support/conversations/{$conversationId}/read")
        ->assertOk()
        ->assertJsonPath('data.stats.admin_unread', 0);
});

test('support broadcast uses private owned channels and excludes sensitive fields', function () {
    $event = new SupportMessageCreated(7, [
        'id' => 10,
        'conversation_id' => 2,
        'sender_id' => 7,
        'sender_role' => 'user',
        'message' => 'Xin hỗ trợ',
        'read_at' => null,
        'created_at' => now()->toISOString(),
    ], [
        'id' => 2,
        'user' => ['id' => 7, 'name' => 'User', 'username' => 'user7'],
    ], ['user_unread' => 0, 'admin_unread' => 1]);

    $channels = $event->broadcastOn();
    $encodedPayload = json_encode($event->broadcastWith(), JSON_THROW_ON_ERROR);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event)->toBeInstanceOf(ShouldDispatchAfterCommit::class)
        ->and($channels)->each->toBeInstanceOf(PrivateChannel::class)
        ->and(collect($channels)->pluck('name')->all())->toBe(['private-users.7.support', 'private-admin.support'])
        ->and($event->broadcastQueue())->toBe('default')
        ->and($event->broadcastAs())->toBe('support.message.created')
        ->and($encodedPayload)->not->toContain('email', 'token', 'password');
});

test('discord job is queued after commit only for user messages', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();
    Event::fake();
    Queue::fake([SendSupportMessageToDiscord::class]);

    $userResponse = $this->actingAs($user)
        ->postJson('/api/client/support/messages', ['message' => 'Gửi Discord'])
        ->assertCreated();
    $conversationId = (int) $userResponse->json('data.conversation.id');

    Queue::assertPushed(SendSupportMessageToDiscord::class, fn (SendSupportMessageToDiscord $job): bool => $job->messageId === (int) $userResponse->json('data.message.id')
        && $job->queue === 'default'
        && $job->afterCommit === true);

    Queue::fake([SendSupportMessageToDiscord::class]);

    $this->actingAs($admin)
        ->postJson("/api/admin-api/support/conversations/{$conversationId}/messages", ['message' => 'Admin trả lời'])
        ->assertCreated();

    Queue::assertNothingPushed();
});

test('discord job is unique retries failures and blocks user mentions', function () {
    $user = User::factory()->create();
    $conversation = supportConversationFor($user);
    $message = SupportMessage::factory()->for($conversation, 'conversation')->create([
        'sender_id' => $user->id,
        'sender_role' => SupportMessage::ROLE_USER,
        'message' => '@everyone cần hỗ trợ ngay',
    ]);
    config([
        'services.discord.channels.support' => 'https://discord.test/webhook',
        'app.url' => 'https://napcarot.test',
    ]);
    Http::preventStrayRequests();
    Http::fake(['https://discord.test/webhook' => Http::response([], 204)]);

    $job = new SendSupportMessageToDiscord($message->id);
    $job->handle(app(DiscordSupportNotifierService::class));

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe((string) $message->id)
        ->and($job->backoff())->toBe([5, 30, 120])
        ->and($job->timeout)->toBeLessThan((int) config('queue.connections.database.retry_after'));

    Http::assertSent(function (Request $request) use ($conversation): bool {
        return $request->url() === 'https://discord.test/webhook'
            && $request['allowed_mentions'] === ['parse' => []]
            && $request['embeds'][0]['url'] === "https://napcarot.test/admin/support?conversation={$conversation->id}"
            && ! str_contains((string) $request['embeds'][0]['description'], '@everyone');
    });
});

test('replaying events or discord jobs never creates duplicate messages', function () {
    Event::fake([SupportMessageCreated::class]);
    $user = User::factory()->create();
    $conversation = supportConversationFor($user);
    $message = SupportMessage::factory()->for($conversation, 'conversation')->create([
        'sender_id' => $user->id,
        'sender_role' => SupportMessage::ROLE_USER,
    ]);
    config(['services.discord.channels.support' => '']);
    $payload = [
        'id' => $message->id,
        'conversation_id' => $conversation->id,
        'sender_id' => $user->id,
        'sender_role' => 'user',
        'message' => $message->message,
    ];

    SupportMessageCreated::dispatch($user->id, $payload, ['id' => $conversation->id], ['user_unread' => 0, 'admin_unread' => 1]);
    SupportMessageCreated::dispatch($user->id, $payload, ['id' => $conversation->id], ['user_unread' => 0, 'admin_unread' => 1]);
    (new SendSupportMessageToDiscord($message->id))->handle(app(DiscordSupportNotifierService::class));
    (new SendSupportMessageToDiscord($message->id))->handle(app(DiscordSupportNotifierService::class));

    expect(SupportMessage::query()->whereBelongsTo($conversation, 'conversation')->count())->toBe(1);
});
