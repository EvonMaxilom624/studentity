<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Security\Authority\AuthorityContractFields as F;

/** Unbound buffer-only preview proof; no storage writes or ordinary access permission. */
final readonly class DocumentPreviewService
{
    public const MAXIMUM_PREVIEW_BYTES = 5242880; // 5MB lab limit for previews

    public function __construct(
        private \Closure $clearanceVerifier,
        private int $maximumBytes = self::MAXIMUM_PREVIEW_BYTES,
        private int $budgetMilliseconds = 5000
    ) {
        if ($maximumBytes < 1 || $budgetMilliseconds < 1) {
            throw new \InvalidArgumentException('Positive preview lab limits required.');
        }
    }

    public function generate(
        QuarantineObjectReference $original,
        string $originalBytes,
        string $previewObjectId,
        \Closure $generator,
        string $previewMediaType,
        \DateTimeImmutable $now
    ): DocumentPreviewReference {
        try {
            $tick = hrtime(true);
            F::uuid($previewObjectId);
            if ($previewObjectId === $original->objectId || ! in_array($previewMediaType, DocumentPreviewReference::PREVIEW_MEDIA_TYPES, true)) {
                throw new \RuntimeException('Preview object must be distinct from original object.');
            }

            // Verify original bytes match original reference before any operation.
            if (strlen($originalBytes) !== $original->byteCount || ! hash_equals($original->sha256, hash('sha256', $originalBytes))) {
                throw new \RuntimeException('Original bytes corrupted or mismatched.');
            }

            // Verify original object is in cleared/safe state; never generate previews for unsafe/quarantined files.
            // Trusted callback must freshly bind exact object, scan, policy and recovery revisions.
            $before = ($this->clearanceVerifier)($original);
            F::digest($before);
            if ((hrtime(true) - $tick) / 1000000 > $this->budgetMilliseconds) {
                throw new \RuntimeException('Preview admission expired.');
            }

            // Generate preview bytes using caller-provided generator.
            $previewBytes = $generator($original, $originalBytes);
            if (! is_string($previewBytes) || $previewBytes === '' || strlen($previewBytes) > $this->maximumBytes) {
                throw new \RuntimeException('Invalid preview bytes produced.');
            }

            // Verify original bytes were NOT mutated during preview generation.
            if (strlen($originalBytes) !== $original->byteCount || ! hash_equals($original->sha256, hash('sha256', $originalBytes))) {
                throw new \RuntimeException('Original bytes were mutated during preview generation.');
            }
            $after = ($this->clearanceVerifier)($original);
            F::digest($after);
            if (! hash_equals($before, $after) || (hrtime(true) - $tick) / 1000000 > $this->budgetMilliseconds) {
                throw new \RuntimeException('Preview clearance changed or expired.');
            }

            $previewDigest = hash('sha256', $previewBytes);
            $previewByteCount = strlen($previewBytes);
            $timeString = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');

            return new DocumentPreviewReference(
                $previewObjectId,
                $original->objectId,
                $original->documentVersionId,
                $previewDigest,
                $previewByteCount,
                $previewMediaType,
                $timeString
            );
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('Document preview generation unavailable.');
        }
    }
}
