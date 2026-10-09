<?php

use App\Features\Auth\Controllers\AuthController;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'apiLogin'])->middleware('throttle:10,1')->name('api.auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/system-settings', function (SettingStore $settingStore) {
        return response()->json(['status' => true, 'data' => ['settings' => $settingStore->getMany([
            'site_name' => config('app.name', 'Tiện ích NRO'), 'site_description' => '', 'support_email' => '',
            'hotline' => '', 'light_logo' => '', 'dark_logo' => '', 'favicon' => '', 'og_image' => '',
            'color_primary' => '#0F172A', 'color_accent' => '#2563EB', 'color_surface' => '#F8FAFC',
        ])]]);
    });

    Route::get('/user', function (Request $request) {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return [
            ...$user->only(['id', 'username', 'email', 'phone', 'full_name', 'avatar', 'role', 'status', 'name']),
            'capabilities' => [
                'platform_admin' => $user->role === 'admin',
            ],
        ];
    });
});

$adminFeatures = [
    'Setting', 'Upload', 'User', 'Mail', 'Queue', 'Feedback', 'Seo', 'AuditLog',
];

foreach ($adminFeatures as $feature) {
    $routes = base_path("app/Features/Admin/{$feature}/routes.php");

    if (file_exists($routes)) {
        require $routes;
    }
}

if (file_exists(base_path('app/Features/NroNotification/routes.php'))) {
    require base_path('app/Features/NroNotification/routes.php');
}
