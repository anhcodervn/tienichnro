<?php

use App\Features\Support\SupportServiceProvider;
use App\Features\Topup\TopupServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\SharedViewServiceProvider;

return [
    AppServiceProvider::class,
    SharedViewServiceProvider::class,
    SupportServiceProvider::class,
    TopupServiceProvider::class,
];
