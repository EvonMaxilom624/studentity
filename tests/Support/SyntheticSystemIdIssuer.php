<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Identifiers\SystemIdAllocation;
use App\Domain\Identifiers\SystemIdIssuer;
use Illuminate\Support\Str;

/** In-memory independent-state model, NOT a durable issuer or approved ID format. */
final class SyntheticSystemIdIssuer implements SystemIdIssuer
{
    private array $operations = [];

    private array $used = [];

    private \Closure $candidate;

    public function __construct(?\Closure $candidate = null)
    {
        if (! app()->environment('testing')) {
            throw new \LogicException('Synthetic issuer requires testing.');
        }
        $this->candidate = $candidate ?? static fn () => 'synthetic-'.Str::uuid();
    }

    public function reserve(string $operationId, string $intentFingerprint): SystemIdAllocation
    {
        $old = $this->operations[$operationId] ?? null;
        if ($old !== null && $old->intentFingerprint !== $intentFingerprint) {
            throw new \RuntimeException('Synthetic operation conflict.');
        }
        if ($old !== null) {
            return $old;
        }
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $id = ($this->candidate)();
            if (! isset($this->used[$id])) {
                $allocation = new SystemIdAllocation($operationId, $intentFingerprint, $id, 'reserved', 1);
                $this->used[$id] = true;

                return $this->operations[$operationId] = $allocation;
            }
        }
        throw new \RuntimeException('Synthetic ID candidates exhausted.');
    }

    public function lookup(string $operationId): ?SystemIdAllocation
    {
        return $this->operations[$operationId] ?? null;
    }

    public function retire(string $operationId): void
    {
        $old = $this->operations[$operationId];
        $this->operations[$operationId] = new SystemIdAllocation($operationId, $old->intentFingerprint, $old->systemId, 'retired', $old->revision + 1);
    }
}
