<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\PrivateDeliveryReader;
use App\Domain\Documents\QuarantineObjectReference;

/** Synthetic adapter only. Explicitly reuses the lab's fingerprint-verified internal read. */
final readonly class LocalPrivateDeliveryLabReader implements PrivateDeliveryReader
{
    public function __construct(private LocalQuarantineLabStore $store) {}

    public function read(QuarantineObjectReference $object): string
    {
        if (! app()->environment('testing')) {
            throw new FileSecurityUnavailable('Private delivery lab unavailable.');
        }

        return $this->store->readForScan($object);
    }
}
