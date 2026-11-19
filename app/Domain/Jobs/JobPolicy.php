<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Security\Authority\CanonicalAuthorityJson;

/** Explicit lab resource policy; these values do not select university retry or timing rules. */
final readonly class JobPolicy
{
    public function __construct(public string $reference, public int $maxAttempts, public int $leaseMilliseconds)
    {
        if ($reference !== 'synthetic-job-policy-v1' || $maxAttempts < 1 || $maxAttempts > 10 || $leaseMilliseconds < 1 || $leaseMilliseconds > 86400000) {
            throw new \InvalidArgumentException('Invalid synthetic job policy.');
        }
    }

    public function fingerprint(): string
    {
        return hash('sha256', CanonicalAuthorityJson::encode(['reference' => $this->reference, 'maxAttempts' => $this->maxAttempts, 'leaseMilliseconds' => $this->leaseMilliseconds]));
    }
}
