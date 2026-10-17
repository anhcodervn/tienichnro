<?php

use App\Features\NroNotification\Jobs\SendZaloEquipmentNotification;
use App\Features\NroNotification\Services\NroNotificationService;
use App\Features\NroNotification\Services\ZaloBotNotificationService;
use App\Models\NroZaloEquipmentSend;
use App\Models\ZaloReceiveNotification;
use Database\Seeders\NroNotificationSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    config()->set('services.zalo_bot', [
        'enabled' => true, 'messages_url' => 'https://zalo.example/api/messages',
        'authorization' => 'Bearer bot-secret', 'account_id' => 1,
        'box_id' => '8033125305662128843', 'recipient_type' => 'group',
        'registration_key' => 'registration-secret',
    ]);
    Http::preventStrayRequests();
});

test('registration rejects missing wrong bot and empty registration keys', function (): void {
    $this->postJson('/api/nro/internal/zalo-receivers', [])->assertForbidden();
    foreach (['wrong', 'bot-secret'] as $key) {
        $this->withToken($key)->postJson('/api/nro/internal/zalo-receivers', [])->assertForbidden();
    }
    config()->set('services.zalo_bot.registration_key', '');
    $this->withToken('')->postJson('/api/nro/internal/zalo-receivers', [])->assertForbidden();
    $this->assertDatabaseCount('zalo_receive_notifications', 0);
});

test('registration validates and saves character server and string IDs idempotently', function (): void {
    $payload = ['zalo_id' => '000123456789012345678', 'char_name' => ' EmPoBaoNo ', 'char_server' => 1];
    $this->withToken('registration-secret')->postJson('/api/nro/internal/zalo-receivers', $payload)
        ->assertCreated()->assertJsonPath('data.char_name', 'empobaono')->assertJsonPath('data.char_server', 1)
        ->assertJsonPath('data.zalo_id', $payload['zalo_id'])->assertJsonPath('data.type_receive', 'SET_ACTIVATION,GOD_ITEM');
    $this->postJson('/api/nro/internal/zalo-receivers', $payload)->assertOk();
    $this->assertDatabaseCount('zalo_receive_notifications', 1);
    $this->postJson('/api/nro/internal/zalo-receivers', [...$payload, 'char_server' => 2])->assertCreated();
    foreach (['char_server' => 99999, 'zalo_id' => '', 'type_receive' => 'UNKNOWN'] as $key => $value) {
        $this->postJson('/api/nro/internal/zalo-receivers', [...$payload, $key => $value])->assertUnprocessable();
    }
    $this->assertDatabaseCount('zalo_receive_notifications', 2);
});

test('parser handles uppercase whitespace and all set suffixes', function (string $item): void {
    $equipment = app(ZaloBotNotificationService::class)->parse(' EmPoBaoNo VỪA ĐÁNH QUÁI MAY MẮN NHẬN ĐƯỢC  '.$item.' ');
    expect($equipment)->toMatchArray(['char_name' => 'empobaono', 'item' => mb_strtolower($item), 'kind' => 'set']);
})->with(['1 trang bị Set kích hoạt', '1 trang bị Set kích hoạt Gohan', '1 trang bị Set kích hoạt Set Thần Vũ Trụ Kaio']);

test('delivery mentions only matching characters on the same server once', function (): void {
    foreach ([['EmPoBaoNo', 1, '000123'], ['empobaono', 1, '000123'], ['empobaono', 2, 'wrong-server'], ['other', 1, 'wrong-char'], ['empobaono', null, 'no-server']] as [$name, $server, $id]) {
        ZaloReceiveNotification::factory()->create(['char_name' => $name, 'char_server' => $server, 'zalo_id' => $id]);
    }
    Http::fake(['zalo.example/*' => Http::response()]);
    $service = app(ZaloBotNotificationService::class);
    $service->sendNotiZalo($service->parse('empobaono vừa đánh quái may mắn nhận được 1 trang bị Set kích hoạt Set Thần Vũ Trụ Kaio'), 1, 'Vũ trụ 1');
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer bot-secret')
        && $request['accountId'] === 1 && $request['recipientId'] === '8033125305662128843'
        && $request['recipientType'] === 'group' && $request['mentionUserIds'] === ['000123']
        && str_contains($request['message'], '🎉 Chúc mừng: 000123')
        && str_contains($request['message'], '🌍 Máy chủ: Vũ trụ 1')
        && str_contains($request['message'], '01 TRANG BỊ SET KÍCH HOẠT SET THẦN VŨ TRỤ KAIO'));
    Http::assertSentCount(1);
});

test('god item includes location without congratulations when unregistered', function (): void {
    Http::fake(['zalo.example/*' => Http::response()]);
    $service = app(ZaloBotNotificationService::class);
    $service->sendNotiZalo($service->parse('empobaono vừa nhặt được Giầy Thần Linh tại Hang băng khu 5'), 1, 'Vũ trụ 1');
    Http::assertSent(fn (Request $request): bool => ! array_key_exists('mentionUserIds', $request->data())
        && ! str_contains($request['message'], 'Chúc mừng:')
        && str_contains($request['message'], '[ ĐỒ THẦN LINH ]')
        && str_contains($request['message'], 'GIẦY THẦN LINH')
        && str_contains($request['message'], '📍 Vị trí: Hang Băng Khu 5')
        && str_contains($request['message'], 'Trang bị nhặt được:'));
});

test('unregistered set is sent and registered god item mentions its receiver', function (): void {
    Http::fake(['zalo.example/*' => Http::response()]);
    $service = app(ZaloBotNotificationService::class);
    $service->sendNotiZalo($service->parse('player vừa đánh quái may mắn nhận được 1 trang bị Set kích hoạt'), 1, 'Vũ trụ 1');
    Http::assertSent(fn (Request $request): bool => ! array_key_exists('mentionUserIds', $request->data()) && ! str_contains($request['message'], 'Chúc mừng:'));
    ZaloReceiveNotification::factory()->create(['char_name' => 'player', 'char_server' => 1, 'zalo_id' => '000123']);
    $service->sendNotiZalo($service->parse('player vừa nhặt được Giày Thần Linh tại Hang băng khu 5'), 1, 'Vũ trụ 1');
    Http::assertSent(fn (Request $request): bool => ($request['mentionUserIds'] ?? []) === ['000123'] && str_contains($request['message'], 'GIÀY THẦN LINH'));
    Http::assertSentCount(2);
});

test('equipment messages use single newlines without blank lines', function (string $content, bool $registered): void {
    Http::fake(['zalo.example/*' => Http::response()]);
    if ($registered) {
        ZaloReceiveNotification::factory()->create(['char_name' => 'player', 'char_server' => 1, 'zalo_id' => '000123']);
    }
    $service = app(ZaloBotNotificationService::class);
    $service->sendNotiZalo($service->parse($content), 1, 'Vũ trụ 1');
    Http::assertSent(fn (Request $request): bool => str_contains($request['message'], "\n")
        && ! str_contains($request['message'], "\n\n")
        && trim($request['message']) === $request['message']);
})->with([
    'set' => 'player vừa đánh quái may mắn nhận được 1 trang bị Set kích hoạt',
    'god item' => 'player vừa nhặt được Giày Thần Linh tại Hang băng khu 5',
])->with([true, false]);

test('unrelated messages are ignored', function (string $text): void {
    expect(app(ZaloBotNotificationService::class)->parse($text))->toBeNull();
})->with(['Broly vừa xuất hiện tại Hang băng khu 5', 'empobaono vừa nhặt được Giầy lưỡng long tại Hang băng khu 5', 'Set kích hoạt', 'empobaono vừa nhặt được đá thần linh giả tại Hang băng khu 5']);

test('disabled bot sends nothing and failed attempts cannot resend the same message', function (): void {
    $service = app(ZaloBotNotificationService::class);
    $equipment = $service->parse('empobaono vừa nhặt được Áo Thần Linh tại Hang băng khu 5');
    config()->set('services.zalo_bot.enabled', false);
    $service->sendNotiZalo($equipment, 1, 'Vũ trụ 1');
    Http::assertNothingSent();
    config()->set('services.zalo_bot.enabled', true);
    Http::fake(['zalo.example/*' => Http::response([], 503)]);
    expect(fn () => (new SendZaloEquipmentNotification($equipment, 1, 'Vũ trụ 1'))->handle($service))->toThrow(RequestException::class);
    expect(NroZaloEquipmentSend::query()->sole()->status)->toBe('failed');
    (new SendZaloEquipmentNotification($equipment, 1, 'Vũ trụ 1'))->handle($service);
    Http::assertSentCount(1);
});

test('collector timestamps metadata casing and event IDs cannot duplicate equipment content', function (string $content): void {
    Queue::fake();
    $service = app(NroNotificationService::class);
    $payload = ['server_code' => 3, 'code' => 'OTHER', 'content' => $content, 'occurred_at' => now()->toISOString(), 'event_id' => 'clone-1', 'metadata' => ['clone' => 1]];
    $first = $service->ingest($payload);
    foreach (range(2, 4) as $clone) {
        $result = $service->ingest([
            ...$payload, 'event_id' => 'clone-'.$clone, 'occurred_at' => now()->addSeconds($clone)->toISOString(),
            'content' => '  '.str_replace(' ', '  ', mb_strtoupper($content)).'  ',
            'metadata' => ['clone' => $clone], 'char_name' => 'messdy', 'code' => 'SET_ACTIVATION',
        ]);
        expect($result['duplicate'])->toBeTrue()->and($result['notify']->id)->toBe($first['notify']->id);
    }
    $this->assertDatabaseCount('notifies', 1);
    Queue::assertPushed(SendZaloEquipmentNotification::class, 1);
    $first['notify']->forceDelete();
    expect($service->ingest([...$payload, 'event_id' => 'after-prune', 'occurred_at' => now()->addDay()->toISOString()])['duplicate'])->toBeTrue();
    Queue::assertPushed(SendZaloEquipmentNotification::class, 1);
})->with([
    'set activation' => 'messdy vừa đánh quái may mắn nhận được 1 trang bị Set kích hoạt Set Cadic M',
    'god item' => 'messdy vừa nhặt được Giày Thần Linh tại Hang băng khu 5',
]);

test('same equipment on another server and different equipment on the same server remain distinct', function (): void {
    Queue::fake();
    $service = app(NroNotificationService::class);
    $payload = ['server_code' => 1, 'code' => 'OTHER', 'content' => 'player vừa đánh quái may mắn nhận được 1 trang bị Set kích hoạt Gohan', 'occurred_at' => now()->toISOString()];
    expect($service->ingest($payload)['duplicate'])->toBeFalse();
    expect($service->ingest([...$payload, 'server_code' => 2])['duplicate'])->toBeFalse();
    expect($service->ingest([...$payload, 'content' => 'player vừa đánh quái may mắn nhận được 1 trang bị Set kích hoạt Set Cadic M'])['duplicate'])->toBeFalse();
    $this->assertDatabaseCount('notifies', 3);
    Queue::assertPushed(SendZaloEquipmentNotification::class, 3);
});

test('duplicate queued jobs are blocked during sending and after successful delivery', function (): void {
    $service = app(ZaloBotNotificationService::class);
    $equipment = $service->parse('messdy vừa đánh quái may mắn nhận được 1 trang bị Set kích hoạt Set Cadic M');
    Http::fake(function () use ($service, $equipment) {
        expect(NroZaloEquipmentSend::query()->sole()->status)->toBe('sending');
        (new SendZaloEquipmentNotification($equipment, 3, 'Vũ trụ 3'))->handle($service);

        return Http::response();
    });
    (new SendZaloEquipmentNotification($equipment, 3, 'Vũ trụ 3'))->handle($service);
    (new SendZaloEquipmentNotification($equipment, 3, 'Vũ trụ 3'))->handle($service);
    Http::assertSentCount(1);
    expect(NroZaloEquipmentSend::query()->sole()->status)->toBe('sent')
        ->and(NroZaloEquipmentSend::query()->sole()->sent_at)->not->toBeNull();
    $this->travel(365)->days();
    (new SendZaloEquipmentNotification($equipment, 3, 'Vũ trụ 3'))->handle($service);
    Http::assertSentCount(1);
});

test('ambiguous connection failures are retained and never automatically resent', function (): void {
    $service = app(ZaloBotNotificationService::class);
    $equipment = $service->parse('player vừa nhặt được Giày Thần Linh tại Hang băng khu 5');
    Http::fake(fn () => throw new ConnectionException('Connection interrupted after sending'));
    expect(fn () => $service->sendNotiZalo($equipment, 1, 'Vũ trụ 1'))->toThrow(ConnectionException::class);
    expect(NroZaloEquipmentSend::query()->sole()->status)->toBe('uncertain');
    Http::fake(['zalo.example/*' => Http::response()]);
    $service->sendNotiZalo($equipment, 1, 'Vũ trụ 1');
    Http::assertNothingSent();
});

test('ingestion queues each equipment event once after commit', function (): void {
    Queue::fake();
    $payload = ['server_code' => 1, 'code' => 'OTHER', 'content' => 'empobaono vừa nhặt được Giầy Thần Linh tại Hang băng khu 5', 'occurred_at' => now()->toISOString(), 'event_id' => 'equipment1'];
    $service = app(NroNotificationService::class);
    $service->ingest($payload);
    expect($service->ingest($payload)['duplicate'])->toBeTrue();
    $service->ingest([...$payload, 'event_id' => 'equipment-alias']);
    Queue::assertPushed(SendZaloEquipmentNotification::class, fn ($job): bool => $job->serverCode === 1 && $job->afterCommit === true && $job->equipment['char_name'] === 'empobaono');
    Queue::assertPushed(SendZaloEquipmentNotification::class, 1);
});
