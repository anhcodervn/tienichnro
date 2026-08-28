<?php

use App\Features\Client\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

if (file_exists(base_path('app/Features/Recharge/routes.php'))) {
    require base_path('app/Features/Recharge/routes.php');
}

if (file_exists(base_path('app/Features/Topup/routes.php'))) {
    require base_path('app/Features/Topup/routes.php');
}

if (file_exists(base_path('app/Features/Client/Wallet/routes.php'))) {
    require base_path('app/Features/Client/Wallet/routes.php');
}

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/system-settings', function (SettingStore $settingStore) {
        return response()->json(['status' => true, 'data' => ['settings' => $settingStore->getMany([
            'site_name' => config('app.name', 'Nạp Carot'), 'site_description' => '', 'support_email' => '',
            'hotline' => '', 'light_logo' => '', 'dark_logo' => '', 'favicon' => '', 'og_image' => '',
            'color_primary' => '#0F172A', 'color_accent' => '#2563EB', 'color_surface' => '#F8FAFC',
        ])]]);
    });

    Route::get('/user', function (Request $request, WalletService $walletService) {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return [
            ...$user->only(['id', 'username', 'email', 'phone', 'full_name', 'avatar', 'role', 'status', 'name']),
            'wallet' => $walletService->getWalletInfo($user),
        ];
    });
});

$adminFeatures = [
    'Setting', 'Upload', 'RechargeConfig', 'RechargeHistory', 'User',
    'WalletTransaction', 'Notifications', 'Mail', 'Queue', 'Feedback', 'Seo', 'Topup',
];

foreach ($adminFeatures as $feature) {
    $routes = base_path("app/Features/Admin/{$feature}/routes.php");

    if (file_exists($routes)) {
        require $routes;
    }
}

if (file_exists(base_path('app/Features/Support/routes.php'))) {
    require base_path('app/Features/Support/routes.php');
}

if (file_exists(base_path('app/Features/Client/Api/routes.php'))) {
    require base_path('app/Features/Client/Api/routes.php');
}

if (file_exists(base_path('app/Features/Admin/Reporting/routes.php'))) {
    require base_path('app/Features/Admin/Reporting/routes.php');
}
