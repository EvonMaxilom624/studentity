<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Coordinates local synthetic document staging, security inspection, and preview generation.
 * Enforces fail-closed security at each boundary. Preserves original bytes untouched.
 */
final readonly class SyntheticDocumentWorkflow
{
    public const DEFAULT_MAX_BYTES = 5242880; // 5 MB

    public function __construct(
        private PrivateQuarantineStore $store,
        private QuarantineInspectionService $inspectionService,
        private DocumentPreviewService $previewService,
        private int $maxBytes = self::DEFAULT_MAX_BYTES
    ) {}

    public function process(
        string $ownerUserId,
        string $documentVersionId,
        string $mediaType,
        #[\SensitiveParameter] string $bytes,
        DateTimeImmutable $now,
        ?\Closure $previewGenerator = null,
        string $previewMediaType = 'image/webp'
    ): SyntheticDocumentClearanceResult {
        try {
            F::uuid($ownerUserId);
            F::uuid($documentVersionId);

            $byteCount = strlen($bytes);
            if ($byteCount < 1 || $byteCount > $this->maxBytes) {
                throw new \InvalidArgumentException('Document size invalid or exceeds bounds.');
            }

            if (! in_array($mediaType, QuarantineObjectReference::MEDIA_TYPES, true)) {
                throw new \InvalidArgumentException('Unsupported media type.');
            }

            $objectId = (string) Str::uuid();
            $sha256 = hash('sha256', $bytes);

            $object = new QuarantineObjectReference(
                $objectId,
                $documentVersionId,
                $ownerUserId,
                $sha256,
                $byteCount,
                $mediaType
            );

            // 1. Stage original file in quarantine
            $this->store->stage($object, $bytes);

            // 2. Perform security inspection
            $scanResult = $this->inspectionService->inspect($object);
            if ($scanResult->status !== 'clean') {
                throw new \RuntimeException('File failed security scan.');
            }

            // 3. Generate preview if generator provided and file is cleared
            $previewReference = null;
            if ($previewGenerator !== null) {
                $previewObjectId = (string) Str::uuid();
                $previewReference = $this->previewService->generate(
                    $object,
                    $bytes,
                    $previewObjectId,
                    $previewGenerator,
                    $previewMediaType,
                    $now
                );
            }

            return new SyntheticDocumentClearanceResult(
                $object,
                $scanResult,
                $previewReference,
                $now->format('Y-m-d\TH:i:s.v\Z')
            );
        } catch (FileSecurityUnavailable $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new FileSecurityUnavailable('Document processing unavailable; object remains quarantined.', 0, $e);
        }
    }
}
