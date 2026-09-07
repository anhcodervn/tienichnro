<?php

test('affiliate dashboard refreshes through a private realtime channel without polling', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/affiliate/index.vue');
    $event = file_get_contents($projectRoot.'/app/Features/Affiliate/Events/AffiliateDashboardUpdated.php');
    $channels = file_get_contents($projectRoot.'/routes/channels.php');

    expect($page)
        ->toContain("import { echo } from '@laravel/echo-vue'")
        ->toContain("const realtimeEventName = '.affiliate.dashboard.updated'")
        ->toContain('echo().private(realtimeChannelName)')
        ->toContain('realtimeChannel.subscribed(scheduleRealtimeRefresh)')
        ->toContain('window.setTimeout(() => void flushRealtimeRefresh(), 250)')
        ->toContain('.stopListening(realtimeEventName, scheduleRealtimeRefresh)')
        ->toContain('echo().leave(realtimeChannelName)')
        ->not->toContain('setInterval')
        ->and($event)
        ->toContain('ShouldBroadcastNow')
        ->toContain('ShouldDispatchAfterCommit')
        ->toContain('ShouldRescue')
        ->toContain('new PrivateChannel("users.{$this->userId}.affiliate")')
        ->toContain("return 'affiliate.dashboard.updated'")
        ->and($channels)
        ->toContain("Broadcast::channel('users.{userId}.affiliate'");
});
