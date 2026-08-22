<?php

use App\Features\Topup\TopupServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\SharedViewServiceProvider;

return [
    AppServiceProvider::class,
    SharedViewServiceProvider::class,
    TopupServiceProvider::class,
];
