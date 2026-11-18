<?php

declare(strict_types=1);

namespace App\Domain\Identifiers;

/** Server-port value, not evidence obtained from a client or a local restore. */
final readonly class SystemIdAllocation
{
    public function __construct(public string $operationId, public string $intentFingerprint, public string $systemId, public string $state, public int $revision)
    {
        if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $operationId) || ! preg_match('/\A[0-9a-f]{64}\z/D', $intentFingerprint) || ! preg_match('/\A[A-Za-z0-9:._-]{1,128}\z/D', $systemId) || ! in_array($state, ['reserved', 'issued', 'retired'], true) || $revision < 1) {
            throw new \InvalidArgumentException('Invalid System ID allocation.');
        }
    }
}
