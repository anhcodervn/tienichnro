<?php

namespace App\Features\Topup\Exceptions;

use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class The9pBalanceUnavailableException extends RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Không thể kiểm tra số dư provider vào lúc này.', 0, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json(ApiResponse::error($this->getMessage()), 502);
    }
}
