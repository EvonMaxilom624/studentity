<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use DateTimeImmutable;

/** Structural scanner DTO only. It is not permission, academic approval or persisted clearance. */
final readonly class FileScanResult
{
    public function __construct(public string $objectFingerprint, public string $policyFingerprint, public string $scanId, public string $scannerId, public string $signatureVersion, public string $status, public ?string $detectedMediaType, public DateTimeImmutable $checkedAt, public bool $typeValid, public bool $structureSafe, public bool $malwareClean, public bool $activeContentSafe, public ?string $attemptNonce = null)
    {
        if ($attemptNonce !== null) {
            F::digest($attemptNonce);
        }
        F::digest($objectFingerprint);
        F::digest($policyFingerprint);
        F::uuid($scanId);
        F::reference($scannerId);
        F::reference($signatureVersion);
        if (! in_array($status, ['clean', 'unsafe', 'error', 'unavailable'], true) || ! in_array($detectedMediaType, [null, 'unsupported', ...QuarantineObjectReference::MEDIA_TYPES], true) || ($status === 'clean' && (! $typeValid || ! $structureSafe || ! $malwareClean || ! $activeContentSafe || ! in_array($detectedMediaType, QuarantineObjectReference::MEDIA_TYPES, true)))) {
            throw new \InvalidArgumentException('Invalid file scan result.');
        }
    }

    public function __debugInfo(): array
    {
        return ['status' => $this->status, 'evidence' => 'redacted'];
    }
}
