<?php

use App\Models\Boss;
use App\Models\CodeNotify;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

function keywordType(string $code, string|array $keywords): array
{
    return test()->postJson('/api/admin-api/nro/notification-types', ['code' => $code, 'name' => 'Loại '.$code, 'type_id' => CodeNotify::query()->where('code', 'OTHER')->firstOrFail()->type_id, 'keywords' => $keywords])->assertCreated()->json('data');
}

test('comma separated keywords normalize whitespace remove empty duplicates and can be cleared', function (): void {
    $type = keywordType('SERVER_NOTICE', '  BẢO   TRÌ , bảo trì, , Đóng máy chủ, ');
    expect($type['keywords'])->toBe(['BẢO TRÌ', 'Đóng máy chủ']);
    $this->patchJson('/api/admin-api/nro/notification-types/'.$type['id'], ['name' => $type['name'], 'type_id' => $type['type_id'], 'keywords' => ''])->assertOk()->assertJsonPath('data.keywords', []);
    $this->seed(NroNotificationSeeder::class);
    expect(CodeNotify::findOrFail($type['id'])->keywords)->toBe([]);
});

test('raw game content is automatically classified and client options and filters use the stored type', function (): void {
    $type = keywordType('SERVER_NOTICE', 'bảo trì, đóng máy chủ');
    $content = 'Máy chủ sẽ ĐÓNG    MÁY CHỦ lúc 12 giờ.';
    $response = $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => $content, 'code' => 'OTHER', 'occurred_at' => now()->toIso8601String()])->assertCreated()->assertJsonPath('data.code', 'SERVER_NOTICE')->assertJsonPath('data.content', $content);
    $this->assertDatabaseHas('notifies', ['id' => $response->json('data.id'), 'code_id' => $type['id']]);
    $this->getJson('/api/nro/notifies?code=SERVER_NOTICE')->assertOk()->assertJsonCount(1, 'data');
    $this->get('/thong-bao-game?code=SERVER_NOTICE')->assertOk()->assertSee($content);
    $definitions = collect($this->getJson('/api/admin-api/nro/notification-types')->assertOk()->json('data.codes'));
    expect($definitions->firstWhere('code', 'SERVER_NOTICE')['keywords'])->toBe(['bảo trì', 'đóng máy chủ']);
    $this->getJson('/api/nro/options')->assertOk()->assertJsonMissingPath('data.notification_types')->assertJsonMissingPath('data.bosses');
});

test('longest matching keyword wins and equal lengths choose the smallest type id', function (): void {
    keywordType('SHORT_MATCH', 'nâng cấp');
    $specific = keywordType('SPECIFIC_MATCH', 'nâng cấp vật phẩm');
    keywordType('LATER_MATCH', 'nâng cấp vật phẩm');
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Bạn vừa nâng cấp vật phẩm thành công', 'occurred_at' => now()->toIso8601String()])->assertCreated()->assertJsonPath('data.code_id', $specific['id']);
});

test('keywords are literal strings and fallback still supports explicit codes and OTHER', function (): void {
    keywordType('LITERAL', '[VIP]+, 10%_!');
    foreach (['Ưu đãi [VIP]+ hôm nay', 'Tặng 10%_! điểm thưởng'] as $content) {
        $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => $content, 'occurred_at' => now()->toIso8601String()])->assertCreated()->assertJsonPath('data.code', 'LITERAL');
    }
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'VIP normal message', 'code' => 'MAINTENANCE', 'occurred_at' => now()->toIso8601String()])->assertCreated()->assertJsonPath('data.code', 'MAINTENANCE');
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'No keywords match', 'occurred_at' => now()->toIso8601String()])->assertCreated()->assertJsonPath('data.code', 'OTHER');
});

test('keyword classification preserves parsed boss lifecycle fields', function (): void {
    $boss = Boss::factory()->create(['name' => 'Broly 3', 'respawn_seconds' => 1800]);
    keywordType('COLLISION', 'Broly 3 vừa xuất hiện tại Rừng Bamboo khu 5, Broly 3 vừa bị tiêu diệt bởi Tester');
    $id = $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Broly 3 vừa xuất hiện tại Rừng Bamboo khu 5', 'occurred_at' => now()->subMinutes(10)->toIso8601String()])->assertCreated()->assertJsonPath('data.code', 'COLLISION')->assertJsonPath('data.boss_id', $boss->id)->json('data.id');
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Broly 3 vừa bị tiêu diệt bởi Tester', 'occurred_at' => now()->subMinutes(5)->toIso8601String()])->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.state', 'dead');
});

test('replayed events keep the original classification after keyword configuration changes', function (): void {
    $type = keywordType('ORIGINAL', 'game notice');
    $payload = ['server_code' => 1, 'content' => 'New game notice', 'event_id' => 'keyword-retry', 'occurred_at' => now()->toIso8601String()];
    $id = $this->postJson('/api/admin-api/nro/notifies', $payload)->assertCreated()->assertJsonPath('data.code', 'ORIGINAL')->json('data.id');
    CodeNotify::findOrFail($type['id'])->update(['keywords' => []]);
    keywordType('REPLACEMENT', 'game notice');
    $this->postJson('/api/admin-api/nro/notifies', $payload)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('data.id', $id)->assertJsonPath('data.code', 'ORIGINAL');
    $this->postJson('/api/admin-api/nro/notifies', array_replace($payload, ['event_id' => 'another-retry']))->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('data.id', $id)->assertJsonPath('data.code', 'ORIGINAL');
    $this->postJson('/api/admin-api/nro/notifies', array_replace($payload, ['content' => 'Next game notice', 'event_id' => 'next-event']))->assertCreated()->assertJsonPath('data.code', 'REPLACEMENT');
    $this->assertDatabaseCount('notifies', 2);
});

test('invalid keyword definitions are rejected', function (mixed $keywords, string $field): void {
    $this->postJson('/api/admin-api/nro/notification-types', ['code' => 'INVALID_KEYWORDS', 'name' => 'Invalid', 'type_id' => 2, 'keywords' => $keywords])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'nested values' => [[[['value']]], 'keywords.0'],
    'numeric values' => [[123], 'keywords.0'],
    'long keyword' => [[str_repeat('x', 101)], 'keywords.0'],
    'too many keywords' => [array_map(fn (int $index): string => 'keyword'.$index, range(1, 51)), 'keywords'],
]);
