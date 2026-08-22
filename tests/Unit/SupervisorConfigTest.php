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
