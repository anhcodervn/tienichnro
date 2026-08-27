<?php

test('topup domain and client delivery are owned by feature modules', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $featureFiles = [
        'app/Features/Client/Topup/Controllers/CheckoutController.php',
        'app/Features/Client/Topup/Controllers/OrderController.php',
        'app/Features/Client/Topup/Requests/StoreOrderRequest.php',
        'app/Features/Client/Topup/routes.php',
        'app/Features/Topup/Contracts/TopupProviderInterface.php',
        'app/Features/Topup/Events/OrderStatusUpdated.php',
        'app/Features/Topup/Events/AdminTopupOrderUpdated.php',
        'app/Features/Topup/Jobs/ProcessTopupOrder.php',
        'app/Features/Topup/Jobs/ProcessTopupRecipient.php',
        'app/Features/Topup/Jobs/SyncTopupRecipientStatus.php',
        'app/Features/Topup/Observers/OrderObserver.php',
        'app/Features/Topup/Observers/OrderRecipientObserver.php',
        'app/Features/Topup/Providers/ManualTopupProvider.php',
        'app/Features/Topup/Providers/AccNroVnTopupProvider.php',
        'app/Features/Topup/Providers/The9pTopupProvider.php',
        'app/Features/Topup/Services/RecipientFulfillmentService.php',
        'app/Features/Topup/Services/TopupProviderResolver.php',
        'app/Features/Topup/Services/OrderService.php',
        'app/Features/Topup/Services/Payments/BankPaymentService.php',
        'app/Features/Topup/Services/TopupService.php',
        'app/Features/Topup/TopupServiceProvider.php',
    ];
    $legacyLogicFiles = [
        'app/Http/Controllers/Client/CheckoutController.php',
        'app/Http/Controllers/Client/OrderController.php',
        'app/Http/Requests/Client/StoreOrderRequest.php',
        'app/Services/Orders/OrderService.php',
        'app/Services/Payments/BankPaymentService.php',
        'app/Services/Topup/TopupService.php',
        'app/Events/OrderStatusUpdated.php',
        'app/Observers/OrderObserver.php',
    ];

    foreach ($featureFiles as $featureFile) {
        expect($projectRoot.'/'.$featureFile)->toBeFile();
    }

    foreach ($legacyLogicFiles as $legacyLogicFile) {
        expect($projectRoot.'/'.$legacyLogicFile)->not->toBeFile();
    }

    $webRoutes = file_get_contents($projectRoot.'/routes/web.php');
    $featureRoutes = file_get_contents($projectRoot.'/app/Features/Client/Topup/routes.php');
    $providers = file_get_contents($projectRoot.'/bootstrap/providers.php');
    $legacyJobBridge = file_get_contents($projectRoot.'/app/Jobs/ProcessTopupOrder.php');

    expect($webRoutes)
        ->toContain("require base_path('app/Features/Client/Topup/routes.php')")
        ->not->toContain('App\\Http\\Controllers\\Client\\CheckoutController')
        ->and($featureRoutes)
        ->toContain("->name('checkout.store')")
        ->toContain("->name('orders.show')")
        ->toContain("->name('account.')")
        ->toContain("->name('orders.index')")
        ->and($providers)
        ->toContain('use App\\Features\\Topup\\TopupServiceProvider;')
        ->toContain('TopupServiceProvider::class')
        ->and($legacyJobBridge)
        ->toContain('Compatibility bridge')
        ->toContain('App\\Features\\Topup\\Jobs\\ProcessTopupOrder');
});
