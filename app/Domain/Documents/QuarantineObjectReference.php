<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityJson;

/** Server-resolved immutable metadata, never an original filename or request-selected storage key. */
final readonly class QuarantineObjectReference
{
    public const MEDIA_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

    public function __construct(public string $objectId, public string $documentVersionId, public string $ownerUserId, public string $sha256, public int $byteCount, public string $mediaType)
    {
        foreach ([$objectId, $documentVersionId, $ownerUserId] as $id) {
            F::uuid($id);
        }
        F::digest($sha256);
        if ($byteCount < 1 || ! in_array($mediaType, self::MEDIA_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid quarantine reference.');
        }
    }

    public function fingerprint(): string
    {
        return hash('sha256', CanonicalAuthorityJson::encode(['purpose' => 'studentity.quarantine.reference.v1'] + get_object_vars($this)));
    }

    public function __debugInfo(): array
    {
        return ['object' => 'redacted'];
    }
}
