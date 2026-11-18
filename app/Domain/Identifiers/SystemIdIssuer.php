<?php

declare(strict_types=1);

namespace App\Domain\Identifiers;

/** Must be independently trustworthy/current; no production implementation is selected. */
interface SystemIdIssuer
{
    public function reserve(string $operationId, string $intentFingerprint): SystemIdAllocation;

    /** null means UNKNOWN, never proof that an operation/ID is free. */
    public function lookup(string $operationId): ?SystemIdAllocation;
}
