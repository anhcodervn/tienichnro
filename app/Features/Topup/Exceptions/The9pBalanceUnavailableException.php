<?php

namespace App\Features\Topup\Exceptions;

use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class The9pBalanceUnavailableException extends RuntimeException
{
    public readonly string $errorCode;

    public function __construct(?\Throwable $previous = null)
    {
        $diagnostic = TopupProviderConnectionException::fromThrowable($previous ?? new RuntimeException);
        $this->errorCode = $diagnostic->errorCode;

        parent::__construct($diagnostic->getMessage(), 0, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json(ApiResponse::error($this->getMessage(), [
            'error_code' => $this->errorCode,
        ]), 502);
    }
}
