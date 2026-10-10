<?php

use App\Features\License\Services\LicenseSessionService;
use App\Models\License;
use App\Models\LicensePlan;
use App\Models\LicenseProduct;
use App\Models\LicenseSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

test('mysql serializes simultaneous activation transfer and old heartbeat without a shared cache lock', function (string $scenario): void {
    if (getenv('LICENSE_MYSQL_TESTS') !== '1') {
        $this->markTestSkipped('Set LICENSE_MYSQL_TESTS=1 for isolated MySQL race tests.');
    }
    $database = 'license_test_'.bin2hex(random_bytes(8));
    $original = DB::getDefaultConnection();
    $connection = config('database.connections.mysql');
    $connection['url'] = null;
    $connection['database'] = $database;
    config(['database.connections.license_admin' => [...$connection, 'database' => null]]);
    $created = false;
    $processes = [];
    try {
        DB::connection('license_admin')->statement('CREATE DATABASE `'.$database.'`');
        $created = true;
        config(['database.connections.license_test' => $connection, 'license.cache_store' => 'array']);
        DB::setDefaultConnection('license_test');
        (require base_path('database/migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require base_path('database/migrations/2026_10_10_155133_create_license_management_tables.php'))->up();
        (require base_path('database/migrations/2026_10_10_161603_add_client_version_to_license_devices_table.php'))->up();
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $product = LicenseProduct::factory()->create();
        $plan = LicensePlan::factory()->create(['product_id' => $product->id]);
        $rawKey = strtoupper(bin2hex(random_bytes(16)));
        $license = License::factory()->create(['product_id' => $product->id, 'plan_id' => $plan->id, 'user_id' => $owner->id, 'key_hash' => LicenseSessionService::hashKey($rawKey)]);
        $pem = openssl_pkey_get_details(openssl_pkey_get_private(file_get_contents(base_path('tests/Fixtures/license-device-test.pem'))))['key'];
        $publicKey = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r"], '', $pem);
        $service = app(LicenseSessionService::class);
        $activation = function (string $uuid) use ($service, $product, $rawKey, $publicKey): array {
            $challenge = $service->challenge();

            return ['license_key' => $rawKey, 'product_code' => $product->product_code, 'device_uuid' => $uuid, 'device_name' => 'RACE-PC', 'public_key' => $publicKey, 'client_version' => '1.0.0', 'challenge_id' => $challenge['challenge_id'], 'nonce' => $challenge['nonce'], 'timestamp' => now()->timestamp];
        };
        $job = fn (string $path, array $payload) => ['connection' => $connection, 'path' => $path, 'payload' => $payload, 'owner_id' => $owner->id];
        $run = function (array $value): Process {
            $process = new Process([PHP_BINARY, base_path('tests/Fixtures/license-concurrency-worker.php')], base_path());
            $process->setInput(json_encode($value))->setTimeout(20)->start();

            return $process;
        };
        $old = null;
        if ($scenario !== 'activation') {
            $initial = $run($job('/api/v1/licenses/activate', $activation((string) Str::uuid())));
            $initial->wait();
            expect($initial->isSuccessful())->toBeTrue($initial->getErrorOutput());
            $old = LicenseSession::query()->firstOrFail();
            $license->refresh()->update(['last_transfer_at' => now()->subMinutes(31)]);
        }
        $jobs = [$job($scenario === 'activation' ? '/api/v1/licenses/activate' : '/api/v1/licenses/transfer', $activation((string) Str::uuid()))];
        if ($scenario === 'heartbeat-transfer') {
            $token = bin2hex(random_bytes(32));
            $old->update(['token_hash' => hash('sha256', $token)]);
            $jobs[] = [...$job('/api/v1/licenses/heartbeat', ['product_code' => $product->product_code, 'device_uuid' => $old->device->device_uuid, 'session_id' => $old->id, 'generation' => $old->generation, 'timestamp' => now()->timestamp, 'nonce' => bin2hex(random_bytes(32))]), 'token' => $token];
        } else {
            $jobs[] = $job($scenario === 'activation' ? '/api/v1/licenses/activate' : '/api/v1/licenses/transfer', $activation((string) Str::uuid()));
        }
        DB::beginTransaction();
        License::query()->whereKey($license->id)->lockForUpdate()->firstOrFail();
        foreach ($jobs as $value) {
            $processes[] = $run($value);
        }
        $deadline = microtime(true) + 10;
        while (count(array_filter($processes, fn ($process) => str_contains($process->getOutput(), 'READY'))) < 2 && microtime(true) < $deadline) {
            usleep(10000);
        }
        expect(count(array_filter($processes, fn ($process) => str_contains($process->getOutput(), 'READY'))))->toBe(2);
        DB::commit();
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
            $lines = explode("\n", trim($process->getOutput()));
            $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
        }
        expect(LicenseSession::query()->where('status', 'active')->count())->toBe(1);
        if ($scenario === 'heartbeat-transfer') {
            expect($results[0]['success'])->toBeTrue()->and($old->fresh()->status)->toBe('revoked');
            expect($license->fresh()->generation)->toBe(2);
        } else {
            expect(count(array_filter($results, fn ($result) => $result['success'])))->toBe(1);
            $denied = array_values(array_filter($results, fn ($result) => ! $result['success']))[0];
            expect($denied['code'])->toBe($scenario === 'activation' ? 'DEVICE_ALREADY_ACTIVE' : 'TRANSFER_COOLDOWN');
        }
    } finally {
        if (DB::getDefaultConnection() === 'license_test' && DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        DB::setDefaultConnection($original);
        DB::purge('license_test');
        if ($created && preg_match('/\Alicense_test_[a-f0-9]{16}\z/', $database) === 1) {
            DB::connection('license_admin')->statement('DROP DATABASE `'.$database.'`');
        }
    }
})->with(['activation', 'transfer', 'heartbeat-transfer']);
