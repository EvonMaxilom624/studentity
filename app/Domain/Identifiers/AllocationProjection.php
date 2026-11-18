<?php

declare(strict_types=1);

namespace App\Domain\Identifiers;

interface AllocationProjection
{
    public function remember(SystemIdAllocation $allocation): void;

    /** Local history only; never authorization to allocate/reuse an ID. */
    public function find(string $operationId): ?SystemIdAllocation;
}
