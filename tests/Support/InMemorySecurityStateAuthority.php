<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Security\AuthorityEvidence;
use App\Domain\Security\AuthorizationContext;
use App\Domain\Security\SecurityStateAuthority;

/** Test-only independent anchor: never bound by application code or restored with MongoDB fixtures. */
final class InMemorySecurityStateAuthority implements SecurityStateAuthority
{
    public array $records = [];

    public bool $unavailable = false;

    public function trust(AuthorizationContext $context): void
    {
        $this->records[$context->sessionDigest] = new AuthorityEvidence($context->userId, $context->sessionDigest, $context->securityEpoch, $context->authorizationDigest(), time() + 300);
    }

    public function evidence(string $userId, string $sessionDigest): ?AuthorityEvidence
    {
        if ($this->unavailable) {
            throw new \RuntimeException('Synthetic authority outage');
        }

        return $this->records[$sessionDigest] ?? null;
    }

    public function revoke(string $userId, string $sessionDigest): void
    {
        if ($this->unavailable) {
            throw new \RuntimeException('Synthetic authority outage');
        }
        $previous = $this->records[$sessionDigest];
        $this->records[$sessionDigest] = new AuthorityEvidence($userId, $sessionDigest, $previous->securityEpoch, $previous->authorizationDigest, time() + 300, revoked: true);
    }
}
