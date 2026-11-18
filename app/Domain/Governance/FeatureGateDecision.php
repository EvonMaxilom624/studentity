<?php

declare(strict_types=1);

namespace App\Domain\Governance;

/** A gate result alone is never authentication, consent or production release authority. */
final readonly class FeatureGateDecision
{
    public function __construct(public bool $allowed, public string $reason) {}
}
