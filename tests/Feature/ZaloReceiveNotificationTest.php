<?php

use App\Models\ZaloReceiveNotification;
use Illuminate\Support\Facades\Schema;

test('zalo subscriptions have the requested columns and preserve string IDs and character names', function (): void {
    expect(Schema::getColumnListing('zalo_receive_notifications'))->toBe(['id', 'box_zalo_id', 'zalo_id', 'char_name', 'type_receive', 'created_at', 'updated_at', 'char_server']);
    $subscription = ZaloReceiveNotification::factory()->create([
        'box_zalo_id' => '0001234567890123456789', 'zalo_id' => '0009876543210987654321',
        'char_name' => 'NhânVật123', 'char_server' => '2', 'type_receive' => 'SET_ACTIVATION,OTHER',
    ])->fresh();
    expect($subscription->box_zalo_id)->toBe('0001234567890123456789')
        ->and($subscription->zalo_id)->toBe('0009876543210987654321')
        ->and($subscription->char_name)->toBe('NhânVật123')
        ->and($subscription->char_server)->toBe(2)
        ->and($subscription->type_receive)->toBe('SET_ACTIVATION,OTHER')
        ->and($subscription->created_at)->not->toBeNull()
        ->and($subscription->updated_at)->not->toBeNull();
});

test('character registrations match both character and server without treating legacy null as all servers', function (): void {
    $first = ZaloReceiveNotification::factory()->create(['char_name' => 'sameplayer', 'char_server' => 1]);
    $second = ZaloReceiveNotification::factory()->create(['char_name' => 'sameplayer', 'char_server' => 2]);
    ZaloReceiveNotification::factory()->create(['char_name' => 'sameplayer', 'char_server' => null]);
    ZaloReceiveNotification::factory()->create(['char_name' => 'otherplayer', 'char_server' => 1]);
    expect(ZaloReceiveNotification::query()->forCharacter('sameplayer', 1)->pluck('id')->all())->toBe([$first->id])
        ->and(ZaloReceiveNotification::query()->forCharacter('sameplayer', 2)->pluck('id')->all())->toBe([$second->id])
        ->and(ZaloReceiveNotification::query()->forCharacter('sameplayer', 3)->count())->toBe(0);
});

test('one Zalo user can register several characters and boxes or receive direct notifications', function (): void {
    ZaloReceiveNotification::factory()->create(['zalo_id' => 'receiver', 'box_zalo_id' => null, 'char_name' => 'player1']);
    ZaloReceiveNotification::factory()->create(['zalo_id' => 'receiver', 'box_zalo_id' => 'box1', 'char_name' => 'player1']);
    ZaloReceiveNotification::factory()->create(['zalo_id' => 'receiver', 'box_zalo_id' => 'box2', 'char_name' => 'player2']);
    $this->assertDatabaseCount('zalo_receive_notifications', 3);
    expect(ZaloReceiveNotification::query()->whereNull('box_zalo_id')->first()->box_zalo_id)->toBeNull();
});

test('comma separated notification types are matched exactly after removing whitespace and duplicates', function (): void {
    $subscription = ZaloReceiveNotification::factory()->make(['type_receive' => ' boss, OTHER, BOSS, ,set_activation,']);
    expect($subscription->notificationTypes())->toBe(['BOSS', 'OTHER', 'SET_ACTIVATION'])
        ->and($subscription->receivesType('BOSS'))->toBeTrue()
        ->and($subscription->receivesType('set_activation'))->toBeTrue()
        ->and($subscription->receivesType('ITEM_UPGRADE'))->toBeFalse()
        ->and($subscription->receivesType('OTHER_EXTRA'))->toBeFalse()
        ->and($subscription->receivesType(''))->toBeFalse();
    $subscription->type_receive = ', ,';
    expect($subscription->notificationTypes())->toBe([])->and($subscription->receivesType('BOSS'))->toBeFalse();
});
