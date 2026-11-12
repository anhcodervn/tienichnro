<?php

namespace App\Features\Captcha\Services;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class CaptchaFailure extends RuntimeException implements ShouldntReport
{
    public function __construct(string $message, public readonly int $httpStatus = 503)
    {
        parent::__construct($message);
    }
}
