<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$heartbeatChannel = app()->environment('production') ? 'ops' : 'staging';

Schedule::command(sprintf('monitor:discord-heartbeat --channel=%s', $heartbeatChannel))
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->when(static fn (): bool => filled(config(sprintf('services.discord.channels.%s', $heartbeatChannel))));

Schedule::command('report:discord-daily-topup')
    ->dailyAt('23:55')
    ->withoutOverlapping()
    ->onOneServer()
    ->when(static fn (): bool => filled(config('services.discord.channels.daily_report')));

Schedule::command('affiliate:release-commissions')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('orders:expire-unpaid')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
