<?php

declare(strict_types=1);

namespace App\Domain\Identifiers;

final class SystemIdAllocator
{
    public function __construct(private SystemIdIssuer $issuer, private AllocationProjection $projection) {}

    public function reserve(string $operationId, string $intentFingerprint): SystemIdAllocation
    {
        try {
            $allocation = $this->issuer->reserve($operationId, $intentFingerprint);
            if ($allocation->operationId !== $operationId || ! hash_equals($allocation->intentFingerprint, $intentFingerprint)) {
                throw new \RuntimeException;
            }
            $this->projection->remember($allocation);

            return $allocation;
        } catch (\Throwable) {
            throw new \RuntimeException('System ID allocation unavailable.');
        }
    }
}
