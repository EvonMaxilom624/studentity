<?php

declare(strict_types=1);

namespace App\Domain\Security;

/** Fail-closed AUD-13 boundary: never restore access solely from a recovered backup. */
final class AuthorizationRecoveryGate
{
    public static function canResume(
        bool $authoritativeSecurityStateVerified,
        bool $sessionRevocationStateVerified,
        bool $roleScopeVerified,
        bool $credentialCompromiseResolved,
        bool $mfaSatisfiedIfRequired,
    ): bool {
        return $authoritativeSecurityStateVerified
            && $sessionRevocationStateVerified
            && $roleScopeVerified
            && $credentialCompromiseResolved
            && $mfaSatisfiedIfRequired;
    }
}
