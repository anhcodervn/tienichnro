<?php

use App\Features\NroNotification\Services\NroNotificationService;
use App\Models\Boss;
use App\Models\CodeNotify;
use App\Models\Notify;
use App\Models\NroEventReceipt;
use App\Models\NroServer;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('mysql workers serialize duplicate deaths and leave older living lifecycles intact', function (): void {
    $connection = config('database.connections.'.config('database.default'));
    if (($connection['driver'] ?? '') !== 'mysql' || ! getenv('NRO_MYSQL_CONCURRENCY_TEST')) {
        $this->markTestSkipped('Opt-in MySQL integration test requires initialized NRO tables.');
    }
    expect(CodeNotify::query()->where('code', 'BOSS')->exists())->toBeTrue();
    $boss = Boss::factory()->create(['name' => 'ConcurrencyBoss'.bin2hex(random_bytes(6))]);
    $server = NroServer::factory()->create();
    $lockHeld = false;
    $readyFiles = [];
    try {
        $service = app(NroNotificationService::class);
        $spawn = ['server_id' => $server->id, 'content' => $boss->name.' vừa xuất hiện tại Rừng Bamboo khu 5', 'occurred_at' => now()->subMinutes(20)->toISOString()];
        $old = $service->ingest($spawn)['notify'];
        $new = $service->ingest([...$spawn, 'occurred_at' => now()->subMinutes(10)->toISOString()])['notify'];
        $death = ['server_id' => $server->id, 'content' => $boss->name.' vừa bị tiêu diệt bởi ConcurrentPlayer', 'occurred_at' => now()->subMinute()->toISOString(), 'event_id' => 'parallel-death'];
        $script = 'require getcwd()."/vendor/autoload.php"; $app = require getcwd()."/bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); file_put_contents($argv[2], "ready"); $result = $app->make(App\Features\NroNotification\Services\NroNotificationService::class)->ingest(json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR)); echo json_encode(["id" => $result["notify"]?->id, "duplicate" => $result["duplicate"]]);';
        $environment = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $connection['database'],
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
        ];
        $readyFiles = [tempnam(sys_get_temp_dir(), 'nro_'), tempnam(sys_get_temp_dir(), 'nro_')];
        DB::beginTransaction();
        $lockHeld = true;
        NroServer::query()->lockForUpdate()->findOrFail($server->id);
        $workers = [];
        foreach ($readyFiles as $readyFile) {
            $workers[] = new Process([PHP_BINARY, '-r', $script, json_encode($death, JSON_THROW_ON_ERROR), $readyFile], base_path(), $environment);
        }
        foreach ($workers as $worker) {
            $worker->start();
        }
        $deadline = microtime(true) + 5;
        while (array_filter($readyFiles, fn (string $file): bool => file_get_contents($file) !== 'ready') && microtime(true) < $deadline) {
            usleep(10000);
        }
        foreach ($readyFiles as $readyFile) {
            expect(file_get_contents($readyFile))->toBe('ready');
        }
        usleep(150000);
        foreach ($workers as $worker) {
            expect($worker->isRunning())->toBeTrue()->and($worker->getOutput())->toBe('');
        }
        DB::commit();
        $lockHeld = false;
        $duplicates = [];
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
            $result = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            expect($result['id'])->toBe($new->id);
            $duplicates[] = $result['duplicate'];
        }
        sort($duplicates);
        expect($duplicates)->toBe([false, true])->and($old->fresh()->death_time)->toBeNull()
            ->and($new->fresh()->killed_by)->toBe('ConcurrentPlayer')
            ->and(Notify::query()->where('server_id', $server->id)->count())->toBe(2);
    } finally {
        if ($lockHeld) {
            DB::rollBack();
        }
        foreach ($workers ?? [] as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        foreach ($readyFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        NroEventReceipt::query()->where('server_id', $server->id)->delete();
        Notify::query()->where('server_id', $server->id)->delete();
        $boss->delete();
        $server->delete();
    }
});
