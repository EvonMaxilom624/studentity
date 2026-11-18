<?php

declare(strict_types=1);

namespace App\Domain\Security;

/** Construct only from server-resolved resource metadata, never request-supplied ownership claims. */
final readonly class ResourceScope
{
    public function __construct(
        public string $type,
        public ?string $id = null,
        public ?string $programId = null,
        public ?string $ownerUserId = null,
    ) {}
}
