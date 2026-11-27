<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Domain\Documents\AttemptCorrelatedFileSecurityScanner;
use App\Domain\Documents\FileScanPolicy;
use App\Domain\Documents\FileScanResult;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\QuarantineObjectReference;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Deterministic local synthetic scanner adapter.
 * Enforces magic bytes, synthetic malware signatures, and active content rules.
 * Local development only; does not grant institutional or production signoff.
 */
final readonly class LocalSyntheticFileSecurityScanner implements AttemptCorrelatedFileSecurityScanner
{
    public const DEFAULT_SCANNER_ID = 'studentity.local-scanner.v1';

    public const DEFAULT_SIGNATURE_VERSION = 'syn-def-2026.11.24';

    public const EICAR_SIGNATURE = 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    public const SYNTHETIC_MALWARE_MARKER = 'STUDENTITY_SYNTHETIC_MALWARE';

    public function __construct(
        private string $scannerId = self::DEFAULT_SCANNER_ID,
        private string $signatureVersion = self::DEFAULT_SIGNATURE_VERSION,
        private bool $forceUnavailable = false,
        private ?\Closure $clock = null
    ) {}

    public function available(FileScanPolicy $policy): bool
    {
        if ($this->forceUnavailable) {
            return false;
        }

        return $policy->scannerId === $this->scannerId
            && $policy->signatureVersion === $this->signatureVersion;
    }

    public function inspect(
        QuarantineObjectReference $object,
        #[\SensitiveParameter] string $bytes,
        FileScanPolicy $policy,
        string $scanId
    ): FileScanResult {
        return $this->inspectAttempt($object, $bytes, $policy, $scanId, null);
    }

    public function inspectAttempt(
        QuarantineObjectReference $object,
        #[\SensitiveParameter] string $bytes,
        FileScanPolicy $policy,
        string $scanId,
        ?string $attemptNonce = null
    ): FileScanResult {
        if (! $this->available($policy)) {
            throw new FileSecurityUnavailable('File security scanner unavailable or policy mismatched.');
        }

        if (strlen($bytes) !== $object->byteCount || ! hash_equals($object->sha256, hash('sha256', $bytes))) {
            throw new FileSecurityUnavailable('File bytes corrupted or mismatched with quarantine reference.');
        }

        $detectedMediaType = $this->detectMediaType($bytes);
        $typeValid = $detectedMediaType === $object->mediaType;

        $malwareClean = ! str_contains($bytes, self::EICAR_SIGNATURE)
            && ! str_contains($bytes, self::SYNTHETIC_MALWARE_MARKER);

        $activeContentSafe = $this->checkActiveContentSafe($bytes, $detectedMediaType);
        $structureSafe = $this->checkStructureSafe($bytes, $detectedMediaType);

        $status = ($typeValid && $malwareClean && $activeContentSafe && $structureSafe) ? 'clean' : 'unsafe';

        $checkedAt = $this->clock !== null
            ? ($this->clock)()
            : new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return new FileScanResult(
            $object->fingerprint(),
            $policy->fingerprint(),
            $scanId,
            $policy->scannerId,
            $policy->signatureVersion,
            $status,
            $detectedMediaType,
            $checkedAt,
            $typeValid,
            $structureSafe,
            $malwareClean,
            $activeContentSafe,
            $attemptNonce
        );
    }

    private function detectMediaType(string $bytes): string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }

        if (str_starts_with($bytes, '%PDF-')) {
            return 'application/pdf';
        }

        return 'unsupported';
    }

    private function checkActiveContentSafe(string $bytes, string $mediaType): bool
    {
        if ($mediaType === 'application/pdf') {
            $dangerousPdfKeywords = ['/JavaScript', '/JS', '/Launch', '/EmbeddedFiles', '/RichMedia'];
            foreach ($dangerousPdfKeywords as $keyword) {
                if (stripos($bytes, $keyword) !== false) {
                    return false;
                }
            }
        }

        $scriptPatterns = ['<?php', '<?=', '<script', '#!/bin/', '#!/usr/bin/'];
        foreach ($scriptPatterns as $pattern) {
            if (stripos($bytes, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }

    private function checkStructureSafe(string $bytes, string $mediaType): bool
    {
        return match ($mediaType) {
            'image/jpeg' => str_contains(substr($bytes, -128), "\xFF\xD9") || strlen($bytes) >= 4,
            'image/png' => str_contains($bytes, 'IEND') || strlen($bytes) >= 8,
            'application/pdf' => str_contains(substr($bytes, -1024), '%%EOF') || strlen($bytes) >= 8,
            default => false,
        };
    }
}
