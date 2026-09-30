<?php

use App\Features\Admin\Setting\Controllers\SiteCustomAssetController;
use App\Features\Auth\Controllers\AuthController;
use App\Features\Client\Affiliate\Controllers\AffiliatePageController;
use App\Features\Client\Affiliate\Controllers\CollaboratorPageController;
use App\Features\Client\Profile\Controllers\ProfilePageController;
use App\Http\Controllers\Account\WalletController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\BioPageController;
use App\Http\Controllers\Client\CrawlerFileController;
use App\Http\Controllers\Client\SitemapController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\PublicContentPageController;
use App\Http\Controllers\PublicSeoPageController;
use App\Http\Controllers\SeoLandingPageController;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/bao-tri', MaintenanceController::class)->name('maintenance');

Route::get('/site-custom.css', [SiteCustomAssetController::class, 'css'])->name('site_custom.css');
Route::get('/site-custom.js', [SiteCustomAssetController::class, 'javascript'])->name('site_custom.js');
Route::get('/robots.txt', [CrawlerFileController::class, 'robots'])->name('robots');
Route::get('/ads.txt', [CrawlerFileController::class, 'ads'])->name('ads');
Route::get('/community', BioPageController::class)->name('bio.show');
Route::get('/cong-tac-vien/{any?}', AffiliatePageController::class)
    ->middleware('site.active')->where('any', '.*')->name('client.affiliate.spa');
Route::get('/dashboard/{any?}', CollaboratorPageController::class)
    ->middleware(['auth', 'role:admin,ctv', 'site.active'])->where('any', '.*')->name('client.collaborator.spa');

Route::middleware(['guest', 'site.active'])->group(function (): void {
    Route::view('/dang-nhap', 'pages.auth.login')->name('auth.login');
    Route::post('/dang-nhap', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login.submit');
    Route::view('/dang-ky', 'pages.auth.register')->name('auth.register');
    Route::post('/dang-ky', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('auth.register.submit');
    Route::redirect('/login', '/dang-nhap')->name('login');
    Route::redirect('/auth/login', '/dang-nhap');
    Route::redirect('/auth/register', '/dang-ky');

    Route::view('/quen-mat-khau', 'pages.auth.forgot-password')->name('password.request');
    Route::post('/quen-mat-khau', [PasswordResetLinkController::class, 'store'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/dat-lai-mat-khau/{token}', function (string $token) {
        return view('pages.auth.reset-password', ['token' => $token, 'email' => request()->string('email')->toString()]);
    })->name('password.reset');
    Route::post('/dat-lai-mat-khau', [NewPasswordController::class, 'store'])->name('password.store');

    Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/dang-xuat', [AuthController::class, 'logout'])->name('logout');
    Route::get('/verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')->name('verification.send');
    Route::prefix('tai-khoan')->name('account.')->group(function (): void {
        Route::get('/', ProfilePageController::class)->defaults('tab', 'profile')->name('index');
        Route::get('/so-du', WalletController::class)->name('wallet');
    });
});

Route::controller(PublicContentPageController::class)->group(function (): void {
    Route::get('/gioi-thieu', 'show')->defaults('slug', 'gioi-thieu')->name('content.about');
    Route::get('/lien-he', 'show')->defaults('slug', 'lien-he')->name('content.contact');
    Route::get('/huong-dan', 'show')->defaults('slug', 'huong-dan')->name('content.guide');
    Route::get('/dieu-khoan-su-dung', 'show')->defaults('slug', 'dieu-khoan-su-dung')->name('content.terms');
    Route::get('/chinh-sach-bao-mat', 'show')->defaults('slug', 'chinh-sach-bao-mat')->name('content.privacy');
    Route::get('/chinh-sach-hoan-tien', 'show')->defaults('slug', 'chinh-sach-hoan-tien')->name('content.refund');
    Route::get('/chinh-sach-thanh-toan', 'show')->defaults('slug', 'chinh-sach-thanh-toan')->name('content.payment');
    Route::get('/cau-hoi-thuong-gap', 'show')->defaults('slug', 'cau-hoi-thuong-gap')->name('content.faq');
});

Route::controller(PublicSeoPageController::class)->group(function (): void {
    Route::get('/tin-tuc', 'index')->name('seo.index');
    Route::get('/tin-tuc/{slug}', 'category')->where('slug', '[a-z0-9-]+')->name('seo.category');
    Route::get('/bai-viet/{slug}', 'legacyShow')->where('slug', '[a-z0-9-]+')->name('seo.legacy.show');
});

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemap-articles.xml', [SitemapController::class, 'articles'])->name('sitemap.articles');
Route::get('/sitemap-categories.xml', [SitemapController::class, 'categories'])->name('sitemap.categories');
Route::get('/sitemap-games.xml', [SitemapController::class, 'games'])->name('sitemap.games');

Route::get('/nap-game-teamobi', SeoLandingPageController::class)
    ->defaults('landingSlug', 'nap-game-teamobi')
    ->name('seo.landing.teamobi');

if (file_exists(base_path('app/Features/Client/Topup/routes.php'))) {
    require base_path('app/Features/Client/Topup/routes.php');
}

Route::get('/{landingSlug}', SeoLandingPageController::class)
    ->whereIn('landingSlug', array_keys(config('seo.landings', [])))
    ->name('seo.landing');

Route::get('/admin/{any?}', function (Request $request, SettingStore $settingStore) {
    $user = $request->user();
    abort_unless($user instanceof User && $user->role === 'admin', 403);

    return view('app', ['systemSettings' => $settingStore->getMany([
        'site_name' => config('app.name', 'Nạp Carot'), 'meta_title' => '', 'meta_description' => '',
        'light_logo' => '', 'dark_logo' => '', 'favicon' => '',
    ])]);
})->middleware('auth')->where('any', '.*')->name('admin.spa');

if (file_exists(base_path('app/Features/Client/Wallet/web.php'))) {
    require base_path('app/Features/Client/Wallet/web.php');
}

if (file_exists(base_path('app/Features/Client/Profile/web.php'))) {
    require base_path('app/Features/Client/Profile/web.php');
}

if (file_exists(base_path('app/Features/Client/Agency/routes.php'))) {
    require base_path('app/Features/Client/Agency/routes.php');
}

if (file_exists(base_path('app/Features/Client/GameService/routes.php'))) {
    require base_path('app/Features/Client/GameService/routes.php');
}

Route::get('/{categorySlug}/{postSlug}', [PublicSeoPageController::class, 'show'])
    ->where([
        'categorySlug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'postSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ])
    ->name('seo.show');
