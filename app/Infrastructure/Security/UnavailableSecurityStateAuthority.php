<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Security\AuthorityEvidence;
use App\Domain\Security\SecurityStateAuthority;
use RuntimeException;

/** Safe default until an independently authenticated/recoverable provider is approved. */
final class UnavailableSecurityStateAuthority implements SecurityStateAuthority
{
    public function evidence(string $userId, string $sessionDigest): ?AuthorityEvidence
    {
        return null;
    }

    public function revoke(string $userId, string $sessionDigest): void
    {
        throw new RuntimeException('Authoritative revocation provider is not configured.');
    }
}
