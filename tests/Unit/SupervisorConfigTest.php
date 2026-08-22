<?php

test('napcarot uses one supervisor file for all long running processes', function () {
    $supervisorDirectory = dirname(__DIR__, 2).'/deploy/supervisor';
    $configFiles = glob($supervisorDirectory.'/*.conf');
    $config = file_get_contents($supervisorDirectory.'/napcarot.conf');

    expect($configFiles)
        ->toHaveCount(1)
        ->and(basename($configFiles[0]))->toBe('napcarot.conf')
        ->and($config)
        ->toContain('[program:napcarot-worker]')
        ->toContain('[program:napcarot-scheduler]')
        ->toContain('[program:napcarot-reverb]')
        ->toContain('[group:napcarot]')
        ->toContain('programs=napcarot-worker,napcarot-scheduler,napcarot-reverb');
});

test('supervisor worker timeout stays below every configured queue retry window', function () {
    $projectRoot = dirname(__DIR__, 2);
    $config = file_get_contents($projectRoot.'/deploy/supervisor/napcarot.conf');
    $queueConfig = require $projectRoot.'/config/queue.php';

    preg_match('/queue:work[^\r\n]*--timeout=(\d+)/', $config, $matches);

    $retryAfterValues = collect($queueConfig['connections'])
        ->pluck('retry_after')
        ->filter(fn (mixed $retryAfter): bool => is_numeric($retryAfter))
        ->map(fn (mixed $retryAfter): int => (int) $retryAfter);

    expect($matches)->toHaveKey(1)
        ->and((int) $matches[1])->toBeLessThan($retryAfterValues->min());
});

test('supervisor worker processes every application queue', function () {
    $projectRoot = dirname(__DIR__, 2);
    $config = file_get_contents($projectRoot.'/deploy/supervisor/napcarot.conf');

    preg_match('/queue:work[^\r\n]*--queue=([^\s]+)/', $config, $matches);

    expect($matches)->toHaveKey(1)
        ->and(explode(',', $matches[1]))->toBe([
            'topup',
            'mails',
            'user-logs',
            'default',
        ]);
});

test('supervisor commands use the production php binary and resilient process options', function () {
    $projectRoot = dirname(__DIR__, 2);
    $config = file_get_contents($projectRoot.'/deploy/supervisor/napcarot.conf');

    expect($config)
        ->toContain('command=/usr/bin/php8.2 /var/www/napcarot.vn/laravel-app/artisan queue:work')
        ->toContain('--sleep=1 --tries=3 --timeout=60 --backoff=3 --memory=256 --max-time=3600 --no-interaction')
        ->toContain('startsecs=10')
        ->toContain('startretries=5')
        ->toContain('stopsignal=TERM')
        ->toContain('environment=HOME="/var/www",USER="www-data"')
        ->not->toContain('xemphatnguoi');
});

test('supervisor uses isolated worker logs and a bounded graceful shutdown window', function () {
    $projectRoot = dirname(__DIR__, 2);
    $config = file_get_contents($projectRoot.'/deploy/supervisor/napcarot.conf');

    preg_match('/\[program:napcarot-worker\](.*?)(?=\r?\n\[program:)/s', $config, $matches);

    expect($matches)->toHaveKey(1)
        ->and($matches[1])
        ->toContain('numprocs=2')
        ->toContain('stopwaitsecs=120')
        ->toContain('stdout_logfile=/var/www/napcarot.vn/laravel-app/storage/logs/queue-worker-%(process_num)02d.log');
});

test('supervisor keeps scheduler and reverb on explicit production commands', function () {
    $projectRoot = dirname(__DIR__, 2);
    $config = file_get_contents($projectRoot.'/deploy/supervisor/napcarot.conf');

    expect($config)
        ->toContain('command=/usr/bin/php8.2 /var/www/napcarot.vn/laravel-app/artisan schedule:work --no-interaction')
        ->toContain('stdout_logfile=/var/www/napcarot.vn/laravel-app/storage/logs/scheduler.log')
        ->toContain('command=/usr/bin/php8.2 /var/www/napcarot.vn/laravel-app/artisan reverb:start --host=127.0.0.1 --port=8082 --no-interaction')
        ->toContain('stdout_logfile=/var/www/napcarot.vn/laravel-app/storage/logs/reverb.log');
});
