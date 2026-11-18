<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Identity\UserKind;

final readonly class AuthorizationContext
{
    /** @param list<RoleGrant> $grants Current, active, unrevoked grants read on the server. */
    public function __construct(
        public string $userId,
        public UserKind $kind,
        public string $accountState,
        public int $securityEpoch,
        public int $sessionEpoch,
        public string $sessionDigest,
        public int $createdAt,
        public int $expiresAt,
        public ?int $revokedAt,
        public ?int $mfaVerifiedAt,
        public array $grants,
        public ?string $sessionRecordId = null,
    ) {}

    public function authorizationDigest(): string
    {
        $grants = array_map(static function (RoleGrant $grant): array {
            $permissions = $grant->permissions;
            ksort($permissions);

            return [$grant->id, $grant->role->value, $grant->programId, $permissions];
        }, $this->grants);
        usort($grants, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return hash('sha256', json_encode([$this->userId, $this->kind->value, $this->accountState, $this->securityEpoch, $grants], JSON_THROW_ON_ERROR));
    }
}
