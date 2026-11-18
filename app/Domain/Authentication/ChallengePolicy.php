<?php

declare(strict_types=1);

namespace App\Domain\Authentication;

/** Inject reviewed server policy. Test values do not select institutional OTP limits. */
final readonly class ChallengePolicy
{
    public function __construct(public int $maxAttempts, public int $lifetimeMilliseconds)
    {
        if ($maxAttempts < 1 || $maxAttempts > 10 || $lifetimeMilliseconds < 1 || $lifetimeMilliseconds > PHP_INT_MAX - 8640000000000000) {
            throw new \InvalidArgumentException('Invalid bounded challenge policy.');
        }
    }
}
