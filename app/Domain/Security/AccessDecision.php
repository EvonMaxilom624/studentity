<?php

declare(strict_types=1);

namespace App\Domain\Security;

final readonly class AccessDecision
{
    public function __construct(public bool $allowed, public string $reason, public int $status = 403) {}
}
