<?php

namespace Tests\Unit;

use App\Domain\Documents\FileScanCommand;
use App\Domain\Documents\FileScanResult;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\QuarantineInspectionService;
use App\Domain\Documents\ScanAttemptJournal;
use PHPUnit\Framework\TestCase;
use Tests\Support\FileSecurityFixtures;

final class ScanJournalBudgetTest extends TestCase
{
    use FileSecurityFixtures;

    public function test_slow_admission_counts_against_monotonic_budget_before_scanner_health(): void
    {
        $journal = new class implements ScanAttemptJournal
        {
            public function begin(FileScanCommand $c, string $n): void
            {
                usleep(100000);
            }

            public function complete(FileScanCommand $c, FileScanResult $r): void
            {
                throw new \RuntimeException('Must not complete.');
            }
        };
        $policy = $this->filePolicy(['roundTripMilliseconds' => 50]);
        $c = new FileScanCommand('66666666-6666-4666-8666-666666666666', $this->fileReference(), $policy, '2026-11-18T00:00:00.000Z');
        $clock = new \DateTimeImmutable;
        $store = $this->fileStore();
        $scanner = $this->fileScanner();
        $service = new QuarantineInspectionService($store, $scanner, fn () => $policy, fn () => true, fn () => $clock, $journal);
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $service->inspectCommand($c)));
        self::assertSame(0, $scanner->healthChecks);
        self::assertSame(0, $store->reads);
        self::assertSame(0, $scanner->scans);
    }
}
