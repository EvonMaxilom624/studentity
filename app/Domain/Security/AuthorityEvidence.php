<?php

declare(strict_types=1);

namespace App\Domain\Security;

/** Evidence must come from independently authenticated authority, never the restored MongoDB snapshot. */
final readonly class AuthorityEvidence
{
    public function __construct(
        public string $userId,
        public string $sessionDigest,
        public int $securityEpoch,
        public string $authorizationDigest,
        public int $validUntil,
        public bool $revoked = false,
        public bool $credentialsSafe = true,
        public bool $requiresReauthentication = false,
    ) {}
}
