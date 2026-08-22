<?php

namespace App\Features\Topup;

use App\Features\Topup\Observers\OrderObserver;
use App\Features\Topup\Observers\OrderRecipientObserver;
use App\Models\Order;
use App\Models\OrderRecipient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class TopupServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('topup-provider', fn (): Limit => Limit::perMinute(60)->by('provider-api'));
        Order::observe(OrderObserver::class);
        OrderRecipient::observe(OrderRecipientObserver::class);
    }
}
