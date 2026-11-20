<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Security\Authority\AuthorityContractFields as F;

/** Unbound bounded-buffer foundation. Network delivery requires its own accepted adapter. */
final readonly class PrivateFileDelivery
{
    public function __construct(private PrivateDeliveryReader $reader, private PrivateDeliveryAuthority $authority, private int $maximumBytes, private int $budgetMilliseconds)
    {
        if ($maximumBytes < 1 || $budgetMilliseconds < 1) {
            throw new \InvalidArgumentException('Explicit positive delivery limits required.');
        }
    }

    public function read(string $actorUserId, QuarantineObjectReference $object, string $operation): string
    {
        try {
            $tick = hrtime(true);
            F::uuid($actorUserId);
            if (! in_array($operation, ['view', 'download'], true) || $object->byteCount > $this->maximumBytes) {
                throw new \RuntimeException;
            }
            $before = $this->authority->witness($actorUserId, $object, $operation);
            F::digest($before);
            if ((hrtime(true) - $tick) / 1000000 > $this->budgetMilliseconds) {
                throw new \RuntimeException;
            }
            $bytes = $this->reader->read($object);
            if (strlen($bytes) !== $object->byteCount || ! hash_equals($object->sha256, hash('sha256', $bytes))) {
                throw new \RuntimeException;
            }
            $after = $this->authority->witness($actorUserId, $object, $operation);
            F::digest($after);
            if (! hash_equals($before, $after) || (hrtime(true) - $tick) / 1000000 > $this->budgetMilliseconds) {
                throw new \RuntimeException;
            }

            return $bytes;
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('Private delivery unavailable; no bytes released.');
        }
    }
}
