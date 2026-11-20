<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Documents\AttemptCorrelatedFileSecurityScanner;
use App\Domain\Documents\FileScanPolicy;
use App\Domain\Documents\FileScanResult;
use App\Domain\Documents\FileSecurityScanner;
use App\Domain\Documents\PrivateQuarantineStore;
use App\Domain\Documents\QuarantineInspectionService;
use App\Domain\Documents\QuarantineObjectReference;
use PHPUnit\Framework\AssertionFailedError;

/** Synthetic assertions only; no real malware detection or production policy is claimed. */
trait FileSecurityFixtures
{
    private function fileBytes(): string
    {
        return "Studentity synthetic quarantine fixture.\n";
    }

    private function fileReference(array $replace = []): QuarantineObjectReference
    {
        return new QuarantineObjectReference(...array_replace(['objectId' => '11111111-1111-4111-8111-111111111111', 'documentVersionId' => '22222222-2222-4222-8222-222222222222', 'ownerUserId' => '33333333-3333-4333-8333-333333333333', 'sha256' => hash('sha256', $this->fileBytes()), 'byteCount' => strlen($this->fileBytes()), 'mediaType' => 'application/pdf'], $replace));
    }

    private function filePolicy(array $replace = []): FileScanPolicy
    {
        return new FileScanPolicy(...array_replace(['reference' => 'synthetic-file-policy-v1', 'scannerId' => 'synthetic-scanner', 'signatureVersion' => 'synthetic-signatures-v1', 'roundTripMilliseconds' => 5000], $replace));
    }

    private function fileResult(QuarantineObjectReference $object, FileScanPolicy $policy, array $replace = []): FileScanResult
    {
        return new FileScanResult(...array_replace(['objectFingerprint' => $object->fingerprint(), 'policyFingerprint' => $policy->fingerprint(), 'scanId' => '44444444-4444-4444-8444-444444444444', 'scannerId' => $policy->scannerId, 'signatureVersion' => $policy->signatureVersion, 'status' => 'clean', 'detectedMediaType' => $object->mediaType, 'checkedAt' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')), 'typeValid' => true, 'structureSafe' => true, 'malwareClean' => true, 'activeContentSafe' => true], $replace));
    }

    private function fileStore(?\Closure $read = null): PrivateQuarantineStore
    {
        return new class($read ?? fn () => $this->fileBytes()) implements PrivateQuarantineStore
        {
            public int $reads = 0;

            public function __construct(private \Closure $read) {}

            public function stage(QuarantineObjectReference $object, #[\SensitiveParameter] string $bytes): void
            {
                throw new \RuntimeException('Inspection never stages.');
            }

            public function readForScan(QuarantineObjectReference $object): string
            {
                $this->reads++;

                return ($this->read)($object);
            }
        };
    }

    private function fileScanner(?\Closure $scan = null, ?\Closure $health = null): FileSecurityScanner
    {
        return new class($scan ?? fn ($o, $b, $p, $id) => $this->fileResult($o, $p, ['scanId' => $id]), $health ?? fn () => true) implements AttemptCorrelatedFileSecurityScanner
        {
            public int $scans = 0;

            public int $healthChecks = 0;

            public function __construct(private \Closure $scan, private \Closure $health) {}

            public function available(FileScanPolicy $policy): bool
            {
                $this->healthChecks++;

                return ($this->health)($policy);
            }

            /** Explicit synthetic capability adapter; real adapters must authenticate their own response binding. */
            public function inspectAttempt(QuarantineObjectReference $object, #[\SensitiveParameter] string $bytes, FileScanPolicy $policy, string $scanId, string $attemptNonce): FileScanResult
            {
                $result = $this->inspect($object, $bytes, $policy, $scanId);

                return new FileScanResult(...array_replace(get_object_vars($result), ['attemptNonce' => $result->attemptNonce ?? $attemptNonce]));
            }

            public function inspect(QuarantineObjectReference $object, #[\SensitiveParameter] string $bytes, FileScanPolicy $policy, string $scanId): FileScanResult
            {
                $this->scans++;

                return ($this->scan)($object, $bytes, $policy, $scanId);
            }
        };
    }

    private function inspectionService(?PrivateQuarantineStore $store = null, ?FileSecurityScanner $scanner = null, ?\Closure $policy = null, ?\Closure $gate = null, ?\Closure $clock = null): QuarantineInspectionService
    {
        return new QuarantineInspectionService($store ?? $this->fileStore(), $scanner ?? $this->fileScanner(), $policy ?? fn () => $this->filePolicy(), $gate ?? fn () => true, $clock ?? fn () => new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    private function fileRejects(\Closure $work): \Throwable
    {
        try {
            $work();
        } catch (AssertionFailedError $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $e;
        }
        self::fail('Expected file security rejection.');
    }
}
