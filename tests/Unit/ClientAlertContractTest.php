<?php

test('Blade notification assets connect escaped flash messages to the shared client bundle', function (): void {
    $root = dirname(__DIR__, 2);
    $layout = file_get_contents($root.'/resources/views/client/layouts/app.blade.php');
    $flash = file_get_contents($root.'/resources/views/client/partials/flash.blade.php');
    $entry = file_get_contents($root.'/resources/js/client.js');
    $script = file_get_contents($root.'/resources/js/client-notifications.js');

    expect($layout)->toContain("@include('client.partials.flash')", "@vite('resources/js/client.js')");
    expect($entry)->toContain("from './client-notifications'", 'initializeClientNotifications()');
    expect($flash)->toContain('data-client-alert', '{{ $error }}', 'auth_google_error');
    expect($script)->toContain("from 'sweetalert2'", 'sweetalert2/dist/sweetalert2.min.css');
});
