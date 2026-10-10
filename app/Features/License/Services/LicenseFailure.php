<?php

namespace App\Features\License\Services;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class LicenseFailure extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly string $errorCode, public readonly int $httpStatus = 403)
    {
        parent::__construct($errorCode);
    }
}
