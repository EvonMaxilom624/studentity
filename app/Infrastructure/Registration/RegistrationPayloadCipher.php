<?php

declare(strict_types=1);

namespace App\Infrastructure\Registration;

use MongoDB\BSON\Binary;

/** Explicit server-owned keys; no environment/global key or random-key fallback. */
final class RegistrationPayloadCipher
{
    public function __construct(public readonly string $keyId, #[\SensitiveParameter] private array $keys)
    {
        if (! isset($keys[$keyId]) || strlen($keys[$keyId]) !== 32) {
            throw new \InvalidArgumentException('Registration encryption key unavailable.');
        }
    }

    public function seal(#[\SensitiveParameter] string $plaintext, string $context): Binary
    {
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $this->keys[$this->keyId], OPENSSL_RAW_DATA, $nonce, $tag, $context, 16);
        if ($ciphertext === false) {
            throw new \RuntimeException('Registration encryption unavailable.');
        }

        return new Binary($nonce.$tag.$ciphertext);
    }

    public function open(Binary $ciphertext, string $keyId, string $context): string
    {
        $bytes = $ciphertext->getData();
        if (! isset($this->keys[$keyId]) || strlen($this->keys[$keyId]) !== 32 || strlen($bytes) < 29 || strlen($bytes) > 32768) {
            throw new \RuntimeException('Registration payload unavailable.');
        }
        $plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $this->keys[$keyId], OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), $context);
        if ($plain === false) {
            throw new \RuntimeException('Registration payload unavailable.');
        }

        return $plain;
    }

    public function __debugInfo(): array
    {
        return ['keys' => 'redacted'];
    }
}
