<?php

namespace App\Support;

class EventStreamRuntime
{
    public function __construct(private readonly string $sapi = PHP_SAPI) {}

    public function durationSeconds(): float
    {
        return $this->sapi === 'cli-server' ? 0 : 25;
    }
}
