<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Identity\UserKind;

final readonly class RoleGrant
{
    public function __construct(
        public string $id,
        public UserKind $role,
        public ?string $programId,
        public array $permissions,
    ) {}
}
