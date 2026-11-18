<?php

declare(strict_types=1);

namespace App\Domain\Security;

interface SecurityStateAuthority
{
    /** Return current trusted evidence, or null when current state cannot be established. Never trust stale cached grants. */
    public function evidence(string $userId, string $sessionDigest): ?AuthorityEvidence;

    /** Idempotently acknowledge durable independent revocation before local state is changed; throw on uncertainty. */
    public function revoke(string $userId, string $sessionDigest): void;
}
