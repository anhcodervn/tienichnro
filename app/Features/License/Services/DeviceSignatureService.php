<?php

namespace App\Features\License\Services;

use Illuminate\Http\Request;

class DeviceSignatureService
{
    public function verify(Request $request, string $publicKey, string $sessionId): void
    {
        $decoded = base64_decode($publicKey, true);
        $key = $decoded === false ? false : openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($decoded), 64, "\n")."-----END PUBLIC KEY-----\n");
        $details = $key === false ? false : openssl_pkey_get_details($key);
        $signature = base64_decode((string) $request->header('X-License-Signature'), true);
        $timestamp = (string) $request->input('timestamp');
        if (! $details || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048 || $signature === false || abs(now()->timestamp - (int) $timestamp) > config('license.timestamp_tolerance')) {
            throw new LicenseFailure('INVALID_SIGNATURE');
        }
        $message = implode("\n", [strtoupper($request->method()), $request->getRequestUri(), hash('sha256', $request->getContent()), $timestamp, (string) $request->input('nonce'), $sessionId]);
        if (openssl_verify($message, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new LicenseFailure('INVALID_SIGNATURE');
        }
    }
}
