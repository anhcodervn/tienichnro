<?php

use App\Models\Setting;
use App\Support\SettingStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    Schema::dropIfExists('settings');
    Schema::create('settings', function ($table): void {
        $table->id();
        $table->string('key')->unique();
        $table->longText('value')->nullable();
        $table->string('type')->nullable();
        $table->timestamps();
    });
});

test('setting store persists individual keys with correct types', function () {
    $store = app(SettingStore::class);

    $store->putMany([
        'site_name' => 'API Bank Việt Nam',
        'site_active' => true,
        'terms_of_use' => [['type' => 'paragraph', 'children' => [['text' => 'Nội dung']]]],
    ]);

    expect(Setting::query()->where('key', 'site_name')->value('type'))->toBe('string');
    expect(Setting::query()->where('key', 'site_active')->value('type'))->toBe('boolean');
    expect(Setting::query()->where('key', 'terms_of_use')->value('type'))->toBe('json');

    expect($store->getMany([
        'site_name' => '',
        'site_active' => false,
        'terms_of_use' => [],
    ]))->toMatchArray([
        'site_name' => 'API Bank Việt Nam',
        'site_active' => true,
        'terms_of_use' => [['type' => 'paragraph', 'children' => [['text' => 'Nội dung']]]],
    ]);
});

test('setting store encrypts sensitive strings at rest', function () {
    $store = app(SettingStore::class);

    $store->putEncryptedString('turnstile_secret_key', 'private-secret');

    $setting = Setting::query()->where('key', 'turnstile_secret_key')->sole();

    expect($setting->type)->toBe('encrypted')
        ->and($setting->value)->not->toBe('private-secret')
        ->and($store->getString('turnstile_secret_key'))->toBe('private-secret');
});
