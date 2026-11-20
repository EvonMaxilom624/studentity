<?php

declare(strict_types=1);

namespace App\Domain\Documents;

interface PrivateDeliveryAuthority
{
    /** Fresh current eligibility revision, never a bearer token or reusable permission. */
    public function witness(string $actorUserId, QuarantineObjectReference $object, string $operation): string;
}
