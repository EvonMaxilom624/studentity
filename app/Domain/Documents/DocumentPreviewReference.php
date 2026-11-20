<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\ReceiptSchema;

/** Immutable preview reference; separate object preserving original quarantined or cleared file. */
final readonly class DocumentPreviewReference
{
    public const PREVIEW_MEDIA_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        public string $previewObjectId,
        public string $originalObjectId,
        public string $documentVersionId,
        public string $sha256,
        public int $byteCount,
        public string $mediaType,
        public string $generatedAt
    ) {
        foreach ([$previewObjectId, $originalObjectId, $documentVersionId] as $id) {
            F::uuid($id);
        }
        if ($previewObjectId === $originalObjectId) {
            throw new \InvalidArgumentException('Preview must be a distinct immutable object preserving original.');
        }
        F::digest($sha256);
        if ($byteCount < 1 || ! in_array($mediaType, self::PREVIEW_MEDIA_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid preview reference metadata.');
        }
        ReceiptSchema::time($generatedAt);
    }

    public function fingerprint(): string
    {
        return hash('sha256', CanonicalAuthorityJson::encode(['purpose' => 'studentity.document.preview.reference.v1'] + get_object_vars($this)));
    }

    public function __debugInfo(): array
    {
        return ['preview' => 'redacted'];
    }
}
