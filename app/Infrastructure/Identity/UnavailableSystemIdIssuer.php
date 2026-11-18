<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Domain\Identifiers\SystemIdAllocation;
use App\Domain\Identifiers\SystemIdIssuer;

final class UnavailableSystemIdIssuer implements SystemIdIssuer
{
    public function reserve(string $operationId, string $intentFingerprint): SystemIdAllocation
    {
        throw new \RuntimeException('System ID issuer unavailable.');
    }

    public function lookup(string $operationId): ?SystemIdAllocation
    {
        return null;
    }
}
