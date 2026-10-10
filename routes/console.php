<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$heartbeatChannel = app()->environment('production') ? 'ops' : 'staging';

Schedule::command('nro:retry-webhooks')->everyMinute()->withoutOverlapping();

Schedule::command(sprintf('monitor:discord-heartbeat --channel=%s', $heartbeatChannel))
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->when(static fn (): bool => filled(config(sprintf('services.discord.channels.%s', $heartbeatChannel))));

Schedule::command('licenses:cleanup')->everyMinute()->withoutOverlapping();
