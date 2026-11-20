<?php

declare(strict_types=1);

namespace App\Domain\Authentication;

use App\Domain\Identity\UserKind;
use App\Domain\Security\Authority\AuthorityContractFields as F;

/** Unbound proposed self-user projection; nullable System ID still requires OpenAPI review. */
final readonly class SelfUserProfile
{
    public const VALID_ACCOUNT_STATES = ['provisional', 'active', 'inactive', 'withdrawn', 'graduated', 'disabled'];

    public function __construct(
        public string $id,
        public ?string $systemId,
        public string $accountState,
        public array $effectiveRoles,
        public string $userKind
    ) {
        F::uuid($id);
        if (! in_array($accountState, self::VALID_ACCOUNT_STATES, true)) {
            throw new \InvalidArgumentException('Invalid account state.');
        }
        if (UserKind::tryFrom($userKind) === null) {
            throw new \InvalidArgumentException('Invalid user kind.');
        }
        foreach ($effectiveRoles as $role) {
            if (! is_string($role) || $role === '' || strlen($role) > 64) {
                throw new \InvalidArgumentException('Invalid effective role.');
            }
        }

        // Opaque issuer reference only; this DTO neither verifies issuance nor selects its format.
        if ($systemId !== null) {
            if ($userKind !== 'student') {
                throw new \InvalidArgumentException('Non-student accounts cannot possess a student System ID.');
            }
            if (! preg_match('/\A[\x21-\x7e]{1,256}\z/D', $systemId)) {
                throw new \InvalidArgumentException('Invalid System ID format.');
            }
        }
    }

    public function toOpenApi(): array
    {
        return [
            'id' => $this->id,
            'systemId' => $this->systemId,
            'accountState' => $this->accountState,
            'effectiveRoles' => array_values($this->effectiveRoles),
        ];
    }
}
