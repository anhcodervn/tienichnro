<?php

use App\Models\LicenseDevice;
use App\Models\LicenseNonce;
use Illuminate\Support\Facades\Cache;

test('redis nonce and lock integration uses a shared cache and database protects replay after key eviction', function (): void {
    if (getenv('LICENSE_REDIS_TESTS') !== '1') {
        $this->markTestSkipped('Set LICENSE_REDIS_TESTS=1 for live Redis integration.');
    }
    $cache = Cache::store('redis');
    $key = 'license:test:'.bin2hex(random_bytes(16));
    $device = LicenseDevice::factory()->create();
    $nonce = hash('sha256', random_bytes(32));
    try {
        expect($cache->add($key, true, 10))->toBeTrue()->and($cache->add($key, true, 10))->toBeFalse();
        $lock = $cache->lock($key.':lock', 10);
        expect($lock->get())->toBeTrue()->and($cache->lock($key.':lock', 10)->get())->toBeFalse();
        $lock->release();
        expect(LicenseNonce::query()->insertOrIgnore(['device_id' => $device->id, 'nonce_hash' => $nonce, 'expires_at' => now()->addMinutes(3)]))->toBe(1);
        $cache->forget($key);
        expect(LicenseNonce::query()->insertOrIgnore(['device_id' => $device->id, 'nonce_hash' => $nonce, 'expires_at' => now()->addMinutes(3)]))->toBe(0);
    } finally {
        $cache->forget($key);
        $cache->forget($key.':lock');
    }
});
