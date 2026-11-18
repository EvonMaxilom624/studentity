<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use App\Domain\Security\Authority\AuthorityContractFields as F;

/** Separately identified recipient-index MAC key; never the OTP verification key. */
final readonly class RegistrationRecipientKey
{
    public function __construct(public string $id, #[\SensitiveParameter] private string $key)
    {
        F::reference($id);
        if (strlen($key) < 32) {
            throw new \InvalidArgumentException('Recipient digest key unavailable.');
        }
    }

    public function digest(RegistrationIntent $intent): string
    {
        return hash_hmac('sha256', 'studentity.registration.recipient.v1:'.$intent->recipient(), $this->key);
    }

    public function __debugInfo(): array
    {
        return ['key' => 'redacted'];
    }
}
