<?php

namespace App\Providers;

use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SharedViewServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(SettingStore $settingStore): void
    {
        ViewFacade::composer('client.layouts.app', function (View $view) use ($settingStore): void {
            $sharedSettings = $settingStore->getMany([
                'site_name' => config('app.name', 'Nạp Carot'),
                'site_domain' => '',
                'site_description' => '',
                'meta_title' => '',
                'meta_description' => '',
                'robots' => 'index,follow',
                'light_logo' => '',
                'dark_logo' => '',
                'favicon' => '',
                'og_image' => '',
                'color_primary' => '#0F172A',
                'color_accent' => '#2563EB',
                'color_surface' => '#F8FAFC',
            ]);
            $viewSettings = $view->getData()['systemSettings'] ?? [];
            $user = auth()->user();

            if ($user instanceof User) {
                $displayName = $user->name ?: $user->email;
                $walletBalance = $user->relationLoaded('wallet')
                    ? $user->wallet?->balance
                    : $user->wallet()->value('balance');

                $view->with('clientAccount', [
                    'avatar' => (string) ($user->avatar ?? ''),
                    'balance' => (string) ($walletBalance ?? '0'),
                    'email' => (string) $user->email,
                    'initial' => Str::upper(Str::substr($displayName, 0, 1)),
                    'name' => $displayName,
                    'role' => (string) $user->role,
                ]);
            }

            $view->with('systemSettings', [
                ...$sharedSettings,
                ...(is_array($viewSettings) ? $viewSettings : []),
            ]);
        });
    }
}
