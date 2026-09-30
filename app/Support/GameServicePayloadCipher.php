<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use UnexpectedValueException;

class GameServicePayloadCipher
{
    /** @param  array<string, mixed>  $payload */
    public function encrypt(array $payload): string
    {
        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed> */
    public function decrypt(string $ciphertext): array
    {
        $payload = json_decode(Crypt::decryptString($ciphertext), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new UnexpectedValueException('Game service order payload must decrypt to an array.');
        }

        return $payload;
    }
}
