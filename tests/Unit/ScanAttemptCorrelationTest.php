<?php

namespace Tests\Unit;

use App\Domain\Documents\AttemptCorrelatedFileSecurityScanner;
use App\Domain\Documents\FileScanCommand;
use App\Domain\Documents\FileScanPolicy;
use App\Domain\Documents\FileScanResult;
use App\Domain\Documents\FileSecurityScanner;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\QuarantineObjectReference;
use PHPUnit\Framework\TestCase;
use Tests\Support\FileSecurityFixtures;

final class ScanAttemptCorrelationTest extends TestCase
{
    use FileSecurityFixtures;

    private function command(): FileScanCommand
    {
        return new FileScanCommand('55555555-5555-4555-8555-555555555555', $this->fileReference(), $this->filePolicy(), '2026-11-18T00:00:00.000Z');
    }

    private function correlated(\Closure $work): AttemptCorrelatedFileSecurityScanner
    {
        return new class($work) implements AttemptCorrelatedFileSecurityScanner
        {
            public int $calls = 0;

            public function __construct(private \Closure $work) {}

            public function available(FileScanPolicy $p): bool
            {
                return true;
            }

            public function inspect(QuarantineObjectReference $o, #[\SensitiveParameter] string $b, FileScanPolicy $p, string $id): FileScanResult
            {
                throw new \RuntimeException('Legacy fallback forbidden');
            }

            public function inspectAttempt(QuarantineObjectReference $o, #[\SensitiveParameter] string $b, FileScanPolicy $p, string $id, string $nonce): FileScanResult
            {
                $this->calls++;

                return ($this->work)($o, $b, $p, $id, $nonce);
            }
        };
    }

    public function test_same_operation_keeps_id_but_receives_distinct_valid_attempt_nonces(): void
    {
        $seen = [];
        $c = $this->command();
        $scanner = $this->correlated(function ($o, $b, $p, $id, $nonce) use (&$seen, $c) {
            self::assertSame($c->scanId, $id);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $nonce);
            $seen[] = $nonce;

            return $this->fileResult($o, $p, ['scanId' => $id, 'attemptNonce' => $nonce]);
        });
        $s = $this->inspectionService(scanner: $scanner);
        self::assertSame($c->scanId, $s->inspectCommand($c)->scanId);
        $s->inspectCommand($c);
        self::assertNotSame($seen[0], $seen[1]);
    }

    public function test_wrong_or_missing_nonce_fails_with_no_legacy_fallback(): void
    {
        foreach ([null, str_repeat('0', 64)] as $nonce) {
            $scanner = $this->correlated(fn ($o, $b, $p, $id, $n) => $this->fileResult($o, $p, ['scanId' => $id, 'attemptNonce' => $nonce]));
            self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $this->inspectionService(scanner: $scanner)->inspectCommand($this->command())));
            self::assertSame(1, $scanner->calls);
        }
    }

    public function test_overlapping_same_id_responses_cannot_cross_attempts_even_with_equal_valid_times(): void
    {
        $at = new \DateTimeImmutable;
        $outer = true;
        $nonces = [];
        $service = null;
        $c = $this->command();
        $scanner = $this->correlated(function ($o, $b, $p, $id, $nonce) use (&$outer, &$nonces, &$service, $c, $at) {
            $nonces[] = $nonce;
            if ($outer) {
                $outer = false;
                self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $service->inspectCommand($c)));
            }

            return $this->fileResult($o, $p, ['scanId' => $id, 'attemptNonce' => $nonces[0], 'checkedAt' => $at]);
        });
        $service = $this->inspectionService(scanner: $scanner, clock: fn () => $at);
        self::assertSame('clean', $service->inspectCommand($c)->status);
        self::assertCount(2, $nonces);
        self::assertNotSame($nonces[0], $nonces[1]);
    }

    public function test_legacy_scanner_cannot_receive_bytes_for_persisted_operation(): void
    {
        $calls = 0;
        $scanner = new class implements FileSecurityScanner
        {
            public int $healthChecks = 0;

            public function available(FileScanPolicy $p): bool
            {
                $this->healthChecks++;

                return true;
            }

            public function inspect(QuarantineObjectReference $o, #[\SensitiveParameter] string $b, FileScanPolicy $p, string $id): FileScanResult
            {
                throw new \RuntimeException;
            }
        };
        $store = $this->fileStore();
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $this->inspectionService($store, $scanner)->inspectCommand($this->command())));
        self::assertSame(0, $store->reads);
        self::assertSame(0, $scanner->healthChecks);
    }

    public function test_nonce_is_optional_for_legacy_results_but_malformed_nonce_is_rejected(): void
    {
        self::assertNull($this->fileResult($this->fileReference(), $this->filePolicy())->attemptNonce);
        $this->expectException(\InvalidArgumentException::class);
        $this->fileResult($this->fileReference(), $this->filePolicy(), ['attemptNonce' => 'invalid']);
    }
}
