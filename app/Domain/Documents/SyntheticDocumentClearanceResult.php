<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Security\Authority\CanonicalAuthorityJson;

/**
 * Immutable clearance result for local synthetic documents.
 * Retains original quarantine reference, completed scan result, and optional preview reference.
 */
final readonly class SyntheticDocumentClearanceResult
{
    public function __construct(
        public QuarantineObjectReference $object,
        public FileScanResult $scanResult,
        public ?DocumentPreviewReference $previewReference,
        public string $clearedAt
    ) {
        if ($scanResult->status !== 'clean') {
            throw new \InvalidArgumentException('Clearance result requires clean scan status.');
        }

        if ($previewReference !== null && $previewReference->originalObjectId !== $object->objectId) {
            throw new \InvalidArgumentException('Preview reference must link to original object ID.');
        }
    }

    public function fingerprint(): string
    {
        return hash('sha256', CanonicalAuthorityJson::encode([
            'purpose' => 'studentity.synthetic.document.clearance.v1',
            'objectId' => $this->object->objectId,
            'objectSha256' => $this->object->sha256,
            'scanId' => $this->scanResult->scanId,
            'status' => $this->scanResult->status,
            'previewId' => $this->previewReference?->previewObjectId,
            'clearedAt' => $this->clearedAt,
        ]));
    }

    public function __debugInfo(): array
    {
        return ['clearance' => 'redacted'];
    }
}
