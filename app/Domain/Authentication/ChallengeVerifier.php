<?php

declare(strict_types=1);

namespace App\Domain\Authentication;

use App\Domain\Registration\RegistrationIntent;
use App\Domain\Security\Authority\CanonicalAuthorityJson;

final class ChallengeVerifier
{
    public function __construct(#[\SensitiveParameter] private string $key)
    {
        if (strlen($key) < 32) {
            throw new \InvalidArgumentException('Strong challenge verification key required.');
        }
    }

    public function digest(string $challengeId, RegistrationIntent $intent, string $purpose, #[\SensitiveParameter] string $code): string
    {
        if (! preg_match('/\A[0-9]{4,12}\z/D', $code)) {
            throw new \InvalidArgumentException('Invalid OTP input envelope.');
        }

        return hash_hmac('sha256', CanonicalAuthorityJson::encode(['domain' => 'studentity.challenge.v1', 'challengeId' => $challengeId, 'intent' => $intent->fingerprint(), 'recipient' => $intent->recipient(), 'purpose' => $purpose, 'code' => $code]), $this->key, true);
    }

    public function recipientDigest(RegistrationIntent $intent): string
    {
        return hash_hmac('sha256', 'studentity.recipient.v1:'.$intent->recipient(), $this->key);
    }

    public function __debugInfo(): array
    {
        return ['verificationKey' => 'redacted'];
    }
}
