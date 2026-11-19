<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Security\Authority\AuthorityContractFields as F;

/** Untrusted ticket until the repository checks the original command, DB token, generation and time. */
final readonly class JobLease
{
    public function __construct(public ReferenceJobCommand $command, public string $token, public int $generation, public \DateTimeImmutable $expiresAt)
    {
        F::uuid($token);
        if ($generation < 1 || $generation > 10) {
            throw new \InvalidArgumentException('Invalid lease generation.');
        }
    }

    public function __debugInfo(): array
    {
        return ['token' => 'redacted', 'generation' => $this->generation];
    }
}
