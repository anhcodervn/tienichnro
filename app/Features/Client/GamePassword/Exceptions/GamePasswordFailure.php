<?php

namespace App\Features\Client\GamePassword\Exceptions;

use RuntimeException;

class GamePasswordFailure extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly bool $uncertain = false)
    {
        parent::__construct($reason);
    }
}
