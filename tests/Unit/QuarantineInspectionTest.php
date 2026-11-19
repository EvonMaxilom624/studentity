<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\QuarantineInspectionService;
use PHPUnit\Framework\TestCase;
use Tests\Support\FileSecurityFixtures;

final class QuarantineInspectionTest extends TestCase
{
    use FileSecurityFixtures;

    public function test_current_synthetic_scan_returns_only_metadata_and_binds_exact_bytes_and_fresh_id(): void
    {
        $seen = [];
        $scanner = $this->fileScanner(function ($o, $b, $p, $id) use (&$seen) {
            $seen[] = $id;
            self::assertSame($this->fileBytes(), $b);

            return $this->fileResult($o, $p, ['scanId' => $id]);
        });
        $s = $this->inspectionService(scanner: $scanner);
        $a = $s->inspect($this->fileReference());
        $b = $s->inspect($this->fileReference());
        self::assertSame('clean', $a->status);
        self::assertSame($seen[0], $a->scanId);
        self::assertNotSame($a->scanId, $b->scanId);
        self::assertSame(2, $scanner->scans);
        self::assertFalse(property_exists($a, 'bytes'));
        self::assertFalse(property_exists($a, 'url'));
        self::assertFalse(property_exists($a, 'allowed'));
        self::assertFalse(property_exists($a, 'academicApproval'));
    }

    public function test_missing_gate_clock_or_policy_and_denied_permission_cannot_read_or_scan(): void
    {
        $store = $this->fileStore();
        $scanner = $this->fileScanner();
        $variants = [new QuarantineInspectionService($store, $scanner), $this->inspectionService($store, $scanner, gate: fn () => false), $this->inspectionService($store, $scanner, policy: fn () => null), $this->inspectionService($store, $scanner, clock: fn () => null)];
        foreach ($variants as $s) {
            self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $s->inspect($this->fileReference())));
        }
        self::assertSame(0, $store->reads);
        self::assertSame(0, $scanner->scans);
    }

    public function test_scanner_down_never_reads_or_scans_and_cached_clean_is_not_reused(): void
    {
        $available = true;
        $store = $this->fileStore();
        $scanner = $this->fileScanner(health: function () use (&$available) {
            return $available;
        });
        $s = $this->inspectionService($store, $scanner);
        self::assertSame('clean', $s->inspect($this->fileReference())->status);
        $available = false;
        $this->fileRejects(fn () => $s->inspect($this->fileReference()));
        self::assertSame(1, $store->reads);
        self::assertSame(1, $scanner->scans);
    }

    public function test_tampered_bytes_fail_before_scanner_and_provider_error_is_redacted(): void
    {
        $scanner = $this->fileScanner();
        $store = $this->fileStore(fn () => 'tampered bytes');
        $this->fileRejects(fn () => $this->inspectionService($store, $scanner)->inspect($this->fileReference()));
        self::assertSame(0, $scanner->scans);
        $scanner = $this->fileScanner(function () {
            throw new \RuntimeException('secret-scanner-token private-file-body');
        });
        $e = $this->fileRejects(fn () => $this->inspectionService(scanner: $scanner)->inspect($this->fileReference()));
        self::assertInstanceOf(FileSecurityUnavailable::class, $e);
        self::assertNull($e->getPrevious());
        self::assertStringNotContainsString('secret-scanner-token', (string) $e);
        self::assertStringNotContainsString('private-file-body', (string) $e);
    }

    public function test_permission_or_policy_changed_during_storage_read_never_sends_bytes_to_scanner(): void
    {
        foreach (['permission', 'policy'] as $change) {
            $active = true;
            $version = 'synthetic-file-policy-v1';
            $scanner = $this->fileScanner();
            $store = $this->fileStore(function () use (&$active, &$version, $change) {
                if ($change === 'permission') {
                    $active = false;
                } else {
                    $version = 'synthetic-file-policy-v2';
                }

return $this->fileBytes();
            });
            $gate = function () use (&$active) {
                return $active;
            };
            $policy = function () use (&$version) {
                return $this->filePolicy(['reference' => $version]);
            };
            $this->fileRejects(fn () => $this->inspectionService($store, $scanner, policy: $policy, gate: $gate)->inspect($this->fileReference()));
            self::assertSame(0, $scanner->scans);
        }
    }

    public function test_permission_policy_or_scanner_availability_lost_during_scan_blocks_result(): void
    {
        foreach (['permission', 'policy', 'scanner'] as $change) {
            $active = true;
            $version = 'synthetic-file-policy-v1';
            $available = true;
            $scanner = $this->fileScanner(function ($o, $b, $p, $id) use (&$active, &$version, &$available, $change) {
                $result = $this->fileResult($o, $p, ['scanId' => $id]);
                if ($change === 'permission') {
                    $active = false;
                } elseif ($change === 'policy') {
                    $version = 'synthetic-file-policy-v2';
                } else {
                    $available = false;
                }

return $result;
            }, function () use (&$available) {
                return $available;
            });
            $gate = function () use (&$active) {
                return $active;
            };
            $policy = function () use (&$version) {
                return $this->filePolicy(['reference' => $version]);
            };
            self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $this->inspectionService(scanner: $scanner, policy: $policy, gate: $gate)->inspect($this->fileReference())));
            self::assertSame(1, $scanner->scans);
        }
    }

    public function test_wrong_tuple_policy_scanner_definition_or_challenge_never_becomes_verified_metadata(): void
    {
        foreach (['objectFingerprint' => str_repeat('f', 64), 'policyFingerprint' => str_repeat('f', 64), 'scannerId' => 'another-scanner', 'signatureVersion' => 'old-definitions', 'scanId' => '55555555-5555-4555-8555-555555555555', 'detectedMediaType' => 'image/png'] as $field => $bad) {
            $scanner = $this->fileScanner(fn ($o, $b, $p, $id) => $this->fileResult($o, $p, array_replace(['scanId' => $id], [$field => $bad])));
            self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $this->inspectionService(scanner: $scanner)->inspect($this->fileReference())));
            self::assertSame(1, $scanner->scans);
        }
    }

    public function test_old_future_error_and_unavailable_results_are_not_clearance(): void
    {
        foreach ([['checkedAt' => new \DateTimeImmutable('-1 day')], ['checkedAt' => new \DateTimeImmutable('+1 day')], ['status' => 'error'], ['status' => 'unavailable']] as $override) {
            $scanner = $this->fileScanner(fn ($o, $b, $p, $id) => $this->fileResult($o, $p, array_replace(['scanId' => $id], $override)));
            self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $this->inspectionService(scanner: $scanner)->inspect($this->fileReference())));
        }
    }

    public function test_unsafe_scan_is_metadata_only_and_cannot_supply_permission_or_released_bytes(): void
    {
        $scanner = $this->fileScanner(fn ($o, $b, $p, $id) => $this->fileResult($o, $p, ['scanId' => $id, 'status' => 'unsafe', 'malwareClean' => false]));
        $r = $this->inspectionService(scanner: $scanner)->inspect($this->fileReference());
        self::assertSame('unsafe', $r->status);
        self::assertFalse($r->malwareClean);
        self::assertFalse(property_exists($r, 'bytes'));
        self::assertFalse(property_exists($r, 'allowed'));
    }

    public function test_slow_storage_and_clock_rollback_fence_before_scan(): void
    {
        $scanner = $this->fileScanner();
        $store = $this->fileStore(function () {
            usleep(100000);

            return $this->fileBytes();
        });
        $this->fileRejects(fn () => $this->inspectionService($store, $scanner, policy: fn () => $this->filePolicy(['roundTripMilliseconds' => 50]))->inspect($this->fileReference()));
        self::assertSame(0, $scanner->scans);
        self::assertSame(1, $store->reads);
        $n = 0;
        $at = new \DateTimeImmutable;
        $clock = function () use (&$n, $at) {
            return $n++ === 0 ? $at : $at->modify('-1 second');
        };
        $this->fileRejects(fn () => $this->inspectionService(scanner: $scanner, clock: $clock)->inspect($this->fileReference()));
        self::assertSame(0, $scanner->scans);
    }

    public function test_slow_scanner_is_rejected_even_when_wall_clock_does_not_advance(): void
    {
        $at = new \DateTimeImmutable;
        $scanner = $this->fileScanner(function ($o, $b, $p, $id) use ($at) {
            usleep(100000);

            return $this->fileResult($o, $p, ['scanId' => $id, 'checkedAt' => $at]);
        });
        self::assertInstanceOf(FileSecurityUnavailable::class,$this->fileRejects(fn () => $this->inspectionService(scanner: $scanner,policy: fn () => $this->filePolicy(['roundTripMilliseconds' => 50]),clock: fn () => $at)->inspect($this->fileReference())));
        self::assertSame(1,$scanner->scans);
    }
}
