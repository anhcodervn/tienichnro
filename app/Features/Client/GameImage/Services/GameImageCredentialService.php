<?php

namespace App\Features\Client\GameImage\Services;

use App\Models\GameImageAccount;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GameImageCredentialService
{
    public function publicKey(string $pem): string
    {
        $key = strlen($pem) <= 4096 ? @openssl_pkey_get_public($pem) : false;
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if (! $details || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048 || $details['bits'] > 4096) {
            throw ValidationException::withMessages(['public_key' => 'Tool must provide an RSA public key (PEM, 2048-4096 bits).']);
        }

        return $details['key'];
    }

    /** @return array<string, int|string> */
    public function encrypt(GameImageAccount $account, string $claimToken): array
    {
        $publicKey = $this->publicKey((string) $account->capture_public_key);
        $key = random_bytes(32);
        $iv = random_bytes(12);
        $aad = 'game-image|'.$account->id.'|'.$account->capture_version.'|'.hash('sha256', $claimToken);
        $ciphertext = openssl_encrypt($account->password, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
        if ($ciphertext === false || strlen($tag) !== 16 || ! openssl_public_encrypt($key, $wrappedKey, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new RuntimeException('Credential encryption failed.');
        }

        return [
            'version' => 1,
            'key_algorithm' => 'RSA-OAEP',
            'oaep_hash' => 'SHA-1',
            'mgf1_hash' => 'SHA-1',
            'content_algorithm' => 'A256GCM',
            'encoding' => 'base64',
            'key_id' => hash('sha256', $publicKey),
            'encrypted_key' => base64_encode($wrappedKey),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'aad' => base64_encode($aad),
            'ciphertext' => base64_encode($ciphertext),
        ];
    }
}
